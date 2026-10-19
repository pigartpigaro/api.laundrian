<?php

namespace App\Services\Transactions;

use App\Models\Customer;
use App\Models\LaundryTransaction;
use App\Models\Setting;

class MemberProgram
{
    public const DEFAULT_DISCOUNT_PERCENT = 20;

    public function configuration(): array
    {
        $values = Setting::query()->whereIn('key', [
            'member_promo_active', 'member_promo_required_transactions', 'member_promo_minimum_subtotal', 'member_promo_discount_percent',
        ])->get()->mapWithKeys(fn (Setting $setting) => [$setting->key => $setting->value['value'] ?? null]);

        return [
            'active' => (bool) ($values['member_promo_active'] ?? false),
            'required_transactions' => (int) ($values['member_promo_required_transactions'] ?? 10),
            'minimum_subtotal' => (float) ($values['member_promo_minimum_subtotal'] ?? 75000),
            'discount_percent' => (float) ($values['member_promo_discount_percent'] ?? self::DEFAULT_DISCOUNT_PERCENT),
        ];
    }

    public function eligibleCount(Customer $customer): int
    {
        $lastRewardId = $this->lastRewardId($customer);

        return (int) LaundryTransaction::query()
            ->where('pelanggan_id', $customer->id)
            ->where('member_promo_eligible', true)
            ->where('status_transaksi', 'AKTIF')
            ->where('status_laundry', 'DIAMBIL')
            ->where('status_bayar', 'LUNAS')
            ->when($lastRewardId, fn ($query) => $query->where('id', '>=', $lastRewardId))
            ->count();
    }

    public function hasRewardReady(Customer $customer, array $configuration): bool
    {
        return $configuration['active'] && $this->eligibleCount($customer) >= $configuration['required_transactions'];
    }

    private function lastRewardId(Customer $customer): ?int
    {
        $id = LaundryTransaction::query()
            ->where('pelanggan_id', $customer->id)
            ->where('member_reward_redeemed', true)
            ->where('status_transaksi', 'AKTIF')
            ->max('id');

        return $id === null ? null : (int) $id;
    }
}
