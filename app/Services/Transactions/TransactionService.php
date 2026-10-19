<?php

namespace App\Services\Transactions;

use App\Models\Customer;
use App\Models\LaundryTransaction;
use App\Models\Service;
use App\Models\TransactionDetail;
use App\Models\TransactionStatusHistory;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class TransactionService
{
    public function __construct(private readonly TransactionNumberGenerator $numbers, private readonly MemberProgram $members) {}

    public function create(User $user, array $data): LaundryTransaction
    {
        return DB::transaction(function () use ($user, $data): LaundryTransaction {
            $serviceIds = collect($data['rincian'])->pluck('layanan_id')->unique();
            $services = Service::query()->whereIn('id', $serviceIds)->where('aktif', true)->lockForUpdate()->get()->keyBy('id');
            if ($services->count() !== $serviceIds->count()) {
                throw ValidationException::withMessages(['rincian' => 'Satu atau beberapa layanan sudah tidak aktif.']);
            }

            $customer = isset($data['pelanggan_id']) ? Customer::query()->where('aktif', true)->lockForUpdate()->find($data['pelanggan_id']) : null;
            if (isset($data['pelanggan_id']) && ! $customer) {
                throw ValidationException::withMessages(['pelanggan_id' => 'Pelanggan tidak aktif atau tidak ditemukan.']);
            }
            if (! $customer && ! empty($data['nama_pelanggan'])) {
                $phone = trim((string) ($data['no_hp'] ?? ''));
                $customer = $phone !== '' ? Customer::query()->where('no_hp', $phone)->where('aktif', true)->lockForUpdate()->first() : null;
                if (! $customer) {
                    $customer = Customer::query()->create([
                        'kode' => 'CUS-'.\Illuminate\Support\Str::upper(\Illuminate\Support\Str::random(10)),
                        'nama' => $data['nama_pelanggan'], 'no_hp' => $phone !== '' ? $phone : null,
                        'aktif' => true,
                    ]);
                }
            }

            $now = now();
            $details = collect($data['rincian'])->map(function (array $item) use ($services): array {
                $service = $services->get($item['layanan_id']);
                $quantity = round((float) $item['qty'], 2);
                $price = (float) $service->harga;

                return [
                    'layanan_id' => $service->id,
                    'nama_layanan' => $service->nama,
                    'qty' => $quantity,
                    'satuan' => $service->jenis_hitung,
                    'harga' => $price,
                    'subtotal' => round($quantity * $price, 2),
                    'catatan' => $item['catatan'] ?? null,
                ];
            });

            $subtotal = round($details->sum('subtotal'), 2);
            $discount = round((float) ($data['diskon'] ?? 0), 2);
            $shipping = round((float) ($data['ongkir'] ?? 0), 2);
            if ($discount > $subtotal) {
                throw ValidationException::withMessages(['diskon' => 'Diskon tidak boleh melebihi subtotal.']);
            }
            $memberConfiguration = $this->members->configuration();
            $memberEligible = $customer && $memberConfiguration['active'] && $subtotal >= $memberConfiguration['minimum_subtotal'];
            $redeemMemberReward = $customer && $this->members->hasRewardReady($customer, $memberConfiguration);
            $memberDiscount = $redeemMemberReward
                ? round(max(0, $subtotal - $discount) * $memberConfiguration['discount_percent'] / 100, 2)
                : 0;
            $total = round($subtotal - $discount - $memberDiscount + $shipping, 2);
            $estimateHours = $services->max('estimasi_jam') ?? 72;
            $transaction = LaundryTransaction::query()->create([
                'notrans' => $this->numbers->next($now),
                'tanggal' => $now,
                'pelanggan_id' => $customer?->id,
                'nama_pelanggan' => $customer?->nama ?? $data['nama_pelanggan'],
                'no_hp' => $customer?->no_hp ?? ($data['no_hp'] ?? null),
                'estimasi_selesai' => $now->copy()->addHours((int) $estimateHours),
                'subtotal' => $subtotal,
                'diskon' => $discount,
                'diskon_member' => $memberDiscount,
                'diskon_member_persen' => $redeemMemberReward ? $memberConfiguration['discount_percent'] : 0,
                'member_reward_redeemed' => $redeemMemberReward,
                'member_promo_eligible' => $memberEligible,
                'ongkir' => $shipping,
                'total' => $total,
                'dibayar' => 0,
                'sisa' => $total,
                'status_bayar' => $total <= 0 ? 'LUNAS' : 'BELUM BAYAR',
                'status_laundry' => 'MASUK',
                'status_transaksi' => 'AKTIF',
                'catatan' => $data['catatan'] ?? null,
                'user_id' => $user->id,
            ]);

            foreach ($details as $detail) {
                $transaction->details()->create($detail);
            }

            TransactionStatusHistory::query()->create([
                'transaksi_id' => $transaction->id,
                'jenis' => 'LAUNDRY',
                'status_dari' => null,
                'status_ke' => 'MASUK',
                'alasan' => 'Transaksi dibuat.',
                'user_id' => $user->id,
            ]);

            return $transaction->load(['customer', 'cashier', 'details.service', 'payments.user', 'statusHistory.user']);
        });
    }

    public function refreshPaymentTotals(LaundryTransaction $transaction): void
    {
        $paid = (float) $transaction->payments()->whereNull('void_at')->sum('nominal');
        $remaining = max(0, round((float) $transaction->total - $paid, 2));
        $transaction->forceFill([
            'dibayar' => $paid,
            'sisa' => $remaining,
            'status_bayar' => $remaining <= 0 ? 'LUNAS' : ($paid > 0 ? 'DP' : 'BELUM BAYAR'),
        ])->save();
    }
}
