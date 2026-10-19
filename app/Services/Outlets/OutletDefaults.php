<?php

namespace App\Services\Outlets;

use App\Models\Customer;
use App\Models\Outlet;
use App\Models\Service;
use Illuminate\Support\Str;

class OutletDefaults
{
    public function seed(Outlet $outlet): void
    {
        foreach ([
            ['Cuci + Setrika', 'KG', 7000, 72], ['Cuci Kering', 'KG', 5000, 72],
            ['Setrika', 'KG', 4000, 72], ['Bed Cover', 'PCS', 20000, 72], ['Sepatu', 'PASANG', 25000, 72],
        ] as [$name, $unit, $price, $hours]) {
            Service::query()->create([
                'outlet_id' => $outlet->id, 'kode' => 'SRV-'.Str::upper(Str::random(10)),
                'nama' => $name, 'jenis_hitung' => $unit, 'harga' => $price, 'estimasi_jam' => $hours, 'aktif' => true,
            ]);
        }
        Customer::query()->create([
            'outlet_id' => $outlet->id, 'kode' => 'UMUM-'.Str::upper(Str::random(8)),
            'nama' => 'Pelanggan Umum', 'poin' => 0, 'aktif' => true,
        ]);
    }
}
