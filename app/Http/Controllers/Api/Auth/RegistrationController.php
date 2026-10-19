<?php

namespace App\Http\Controllers\Api\Auth;

use App\Http\Controllers\Controller;
use App\Models\Outlet;
use App\Models\User;
use App\Services\Outlets\OutletDefaults;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\Rules\Password;

class RegistrationController extends Controller
{
    public function store(Request $request, OutletDefaults $defaults): JsonResponse
    {
        $request->merge(['username' => strtolower(trim((string) $request->input('username')))]);
        $data = $request->validate([
            'outlet_name' => ['required', 'string', 'max:150'],
            'name' => ['required', 'string', 'max:120'],
            'username' => ['required', 'string', 'min:3', 'max:40', 'alpha_dash:ascii', 'unique:users,username'],
            'password' => ['required', 'confirmed', Password::min(8)],
        ]);

        [$outlet, $user] = DB::transaction(function () use ($data, $defaults): array {
            $outlet = Outlet::query()->create([
                'nama' => $data['outlet_name'], 'status' => 'DEMO', 'demo_mulai' => now(), 'demo_selesai' => now()->addDays(2),
            ]);
            $user = User::query()->create([
                'outlet_id' => $outlet->id, 'name' => $data['name'], 'username' => strtolower($data['username']),
                'password' => Hash::make($data['password']), 'role' => 'OWNER', 'is_active' => true,
            ]);
            $defaults->seed($outlet);
            return [$outlet, $user];
        });

        return response()->json(['data' => [
            'token' => $user->createToken('Londrian Web')->plainTextToken,
            'token_type' => 'Bearer',
            'user' => ['id' => $user->id, 'name' => $user->name, 'username' => $user->username, 'role' => $user->role, 'outlet_id' => $outlet->id],
            'outlet' => ['id' => $outlet->id, 'nama' => $outlet->nama, 'status' => $outlet->status],
        ]], 201);
    }
}
