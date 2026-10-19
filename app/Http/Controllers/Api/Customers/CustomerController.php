<?php

namespace App\Http\Controllers\Api\Customers;

use App\Http\Controllers\Controller;
use App\Models\Customer;
use App\Services\Transactions\MemberProgram;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

class CustomerController extends Controller
{
    public function index(Request $request, MemberProgram $members): JsonResponse
    {
        $data = $request->validate([
            'q' => ['nullable', 'string', 'max:100'],
            'per_page' => ['nullable', 'integer', 'min:1', 'max:100'],
        ]);

        $configuration = $members->configuration();
        $customers = Customer::query()->withCount(['transactions as member_eligible_count' => fn ($query) => $query
                ->where('member_promo_eligible', true)->where('status_transaksi', 'AKTIF')->where('status_laundry', 'DIAMBIL')->where('status_bayar', 'LUNAS')
                ->whereRaw('transaksis.id >= COALESCE((SELECT MAX(redeemed.id) FROM transaksis AS redeemed WHERE redeemed.pelanggan_id = pelanggans.id AND redeemed.outlet_id = pelanggans.outlet_id AND redeemed.member_reward_redeemed = 1 AND redeemed.status_transaksi = ?), 0)', ['AKTIF'])])
            ->where('aktif', true)
            ->when($data['q'] ?? null, fn ($query, $term) => $query->where(function ($q) use ($term): void {
                $q->where('nama', 'like', "%{$term}%")->orWhere('no_hp', 'like', "%{$term}%")->orWhere('kode', 'like', "%{$term}%");
            }))
            ->orderBy('nama')->orderBy('id')->cursorPaginate($data['per_page'] ?? 30);

        $customers->setCollection($customers->getCollection()->map(function (Customer $customer) use ($configuration): Customer {
            $customer->setAttribute('member_promo_active', $configuration['active']);
            $customer->setAttribute('member_promo_required_transactions', $configuration['required_transactions']);
            $customer->setAttribute('member_promo_minimum_subtotal', $configuration['minimum_subtotal']);
            $customer->setAttribute('member_promo_discount_percent', $configuration['discount_percent']);

            return $customer;
        }));

        return response()->json($customers);
    }

    public function store(Request $request): JsonResponse
    {
        $data = $this->validated($request);
        $customer = Customer::query()->create(['kode' => 'CUS-'.Str::upper(Str::random(10))] + $data);

        return response()->json(['data' => $customer], 201);
    }

    public function update(Request $request, Customer $customer): JsonResponse
    {
        $customer->update($this->validated($request, true));

        return response()->json(['data' => $customer->refresh()]);
    }

    private function validated(Request $request, bool $partial = false): array
    {
        $rules = [
            'nama' => [$partial ? 'sometimes' : 'required', 'string', 'max:150'],
            'no_hp' => ['nullable', 'string', 'max:32'],
            'alamat' => ['nullable', 'string', 'max:2000'],
            'catatan' => ['nullable', 'string', 'max:2000'],
            'aktif' => ['sometimes', 'boolean'],
        ];

        return $request->validate($rules);
    }
}
