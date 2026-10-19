<?php

namespace App\Http\Controllers\Api\Transactions;

use App\Http\Controllers\Controller;
use App\Models\LaundryTransaction;
use App\Models\Payment;
use App\Services\Transactions\TransactionService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

class PaymentController extends Controller
{
    public function store(Request $request, LaundryTransaction $transaction, TransactionService $service): JsonResponse
    {
        $data = $request->validate([
            'nominal' => ['required', 'numeric', 'gt:0', 'max:9999999999'],
            'metode' => ['required', Rule::in(['TUNAI', 'TRANSFER', 'QRIS'])],
            'keterangan' => ['nullable', 'string', 'max:255'],
        ]);

        return DB::transaction(function () use ($request, $transaction, $service, $data): JsonResponse {
            $locked = LaundryTransaction::query()->whereKey($transaction->id)->lockForUpdate()->firstOrFail();
            if ($locked->status_transaksi !== 'AKTIF') {
                throw ValidationException::withMessages(['transaksi' => 'Pembayaran tidak dapat ditambahkan ke transaksi batal.']);
            }
            if ($locked->status_laundry !== 'SELESAI') {
                throw ValidationException::withMessages(['pembayaran' => 'Pembayaran baru dapat dicatat setelah proses laundry selesai.']);
            }
            if ((float) $data['nominal'] > (float) $locked->sisa) {
                throw ValidationException::withMessages(['nominal' => 'Pembayaran tidak boleh melebihi sisa tagihan.']);
            }

            $payment = $locked->payments()->create([
                'tanggal' => now(), 'nominal' => $data['nominal'], 'metode' => $data['metode'],
                'keterangan' => $data['keterangan'] ?? null, 'user_id' => $request->user()->id,
            ]);
            $service->refreshPaymentTotals($locked);

            return response()->json(['data' => $payment->load('user:id,name'), 'transaksi' => $locked->refresh()], 201);
        });
    }

    public function void(Request $request, Payment $payment, TransactionService $service): JsonResponse
    {
        $data = $request->validate(['alasan_void' => ['required', 'string', 'min:5', 'max:1000']]);

        return DB::transaction(function () use ($request, $payment, $service, $data): JsonResponse {
            $locked = Payment::query()->whereKey($payment->id)->lockForUpdate()->firstOrFail();
            if ($locked->void_at) {
                throw ValidationException::withMessages(['pembayaran' => 'Pembayaran ini sudah di-void.']);
            }
            $locked->update(['void_by' => $request->user()->id, 'void_at' => now(), 'alasan_void' => $data['alasan_void']]);
            $transaction = LaundryTransaction::query()->whereKey($locked->transaksi_id)->lockForUpdate()->firstOrFail();
            $service->refreshPaymentTotals($transaction);

            return response()->json(['data' => $locked->load('user:id,name'), 'transaksi' => $transaction->refresh()]);
        });
    }
}
