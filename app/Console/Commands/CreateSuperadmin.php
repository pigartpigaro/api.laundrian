<?php

namespace App\Console\Commands;

use App\Models\User;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Validator;

class CreateSuperadmin extends Command
{
    protected $signature = 'londrian:make-superadmin';
    protected $description = 'Buat akun superadmin Londrian';

    public function handle(): int
    {
        $name = $this->ask('Nama superadmin');
        $username = strtolower((string) $this->ask('Username superadmin'));
        $password = $this->secret('Password (minimal 8 karakter)');
        $confirmation = $this->secret('Ulangi password');
        $validator = Validator::make([
            'name' => $name, 'username' => $username, 'password' => $password, 'password_confirmation' => $confirmation,
        ], [
            'name' => ['required', 'string', 'max:120'],
            'username' => ['required', 'string', 'min:3', 'max:40', 'alpha_dash:ascii', 'unique:users,username'],
            'password' => ['required', 'string', 'min:8', 'confirmed'],
        ]);
        if ($validator->fails()) {
            foreach ($validator->errors()->all() as $error) $this->error($error);
            return self::FAILURE;
        }
        User::query()->create(['name' => $name, 'username' => $username, 'password' => Hash::make($password), 'role' => 'SUPERADMIN', 'is_active' => true]);
        $this->info("Akun superadmin {$username} berhasil dibuat.");
        return self::SUCCESS;
    }
}
