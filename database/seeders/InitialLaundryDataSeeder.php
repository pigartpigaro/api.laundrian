<?php

namespace Database\Seeders;

use App\Models\Customer;
use App\Models\Service;
use App\Models\Outlet;
use Illuminate\Database\Seeder;

class InitialLaundryDataSeeder extends Seeder
{
    public function run(): void
    {
        $outlet = Outlet::query()->firstOrCreate(['nama' => 'Londrian App'], ['status' => 'DEMO', 'demo_mulai' => now(), 'demo_selesai' => now()->addDays(2)]);
        foreach ([
            ['SRV-001', 'Cuci + Setrika', 'KG', 7000, 24],
            ['SRV-002', 'Cuci Kering', 'KG', 5000, 24],
            ['SRV-003', 'Setrika', 'KG', 4000, 12],
            ['SRV-004', 'Bed Cover', 'PCS', 20000, 48],
            ['SRV-005', 'Sepatu', 'PASANG', 25000, 72],
        ] as [$code, $name, $unit, $price, $eta]) {
            Service::query()->firstOrCreate(['outlet_id' => $outlet->id, 'kode' => $code], [
                'nama' => $name,
                'jenis_hitung' => $unit,
                'harga' => $price,
                'estimasi_jam' => $eta,
                'aktif' => true,
            ]);
        }

        Customer::query()->firstOrCreate(['outlet_id' => $outlet->id, 'kode' => 'UMUM'], [
            'nama' => 'Pelanggan Umum',
            'no_hp' => null,
            'poin' => 0,
            'aktif' => true,
        ]);
    }
}
