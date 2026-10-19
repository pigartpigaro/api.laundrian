<?php

namespace App\Console\Commands;

use App\Models\User;
use App\Models\Outlet;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Validator;

class CreateOwner extends Command
{
    protected $signature = 'londrian:make-owner';
    protected $description = 'Buat akun owner Londrian pertama';

    public function handle(): int
    {
        $name = $this->ask('Nama owner');
        $username = strtolower((string) $this->ask('Username owner'));
        $password = $this->secret('Password (minimal 8 karakter)');
        $confirmation = $this->secret('Ulangi kata sandi');

        $validator = Validator::make([
            'name' => $name,
            'username' => $username,
            'password' => $password,
            'password_confirmation' => $confirmation,
        ], [
            'name' => ['required', 'string', 'max:120'],
            'username' => ['required', 'string', 'min:3', 'max:40', 'alpha_dash:ascii', 'unique:users,username'],
            'password' => ['required', 'string', 'min:8', 'confirmed'],
        ]);

        if ($validator->fails()) {
            foreach ($validator->errors()->all() as $error) {
                $this->error($error);
            }

            return self::FAILURE;
        }

        $outlet = Outlet::query()->firstOrCreate(['nama' => 'Londrian'], ['status' => 'DEMO', 'demo_mulai' => now(), 'demo_selesai' => now()->addDays(2)]);
        User::query()->create(['outlet_id' => $outlet->id, 'name' => $name, 'username' => $username, 'password' => Hash::make($password), 'role' => 'OWNER', 'is_active' => true]);
        $this->info("Akun owner {$username} berhasil dibuat.");

        return self::SUCCESS;
    }
}
