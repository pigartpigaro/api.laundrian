<?php

namespace App\Http\Controllers\Api\Auth;

use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\Rules\Password;
use Illuminate\Validation\ValidationException;
use Laravel\Sanctum\PersonalAccessToken;

class AuthController extends Controller
{
    public function login(Request $request): JsonResponse
    {
        $data = $request->validate([
            'username' => ['required', 'string'],
            'password' => ['required', 'string', Password::min(8)],
            'device_name' => ['sometimes', 'string', 'max:100'],
        ]);

        return $this->issueToken($data, $data['device_name'] ?? 'Londrian Web');
    }

    public function token(Request $request): JsonResponse
    {
        $data = $request->validate([
            'username' => ['required', 'string'],
            'password' => ['required', 'string', Password::min(8)],
            'device_name' => ['required', 'string', 'max:100'],
        ]);

        return $this->issueToken($data, $data['device_name'], 201);
    }

    private function issueToken(array $data, string $deviceName, int $status = 200): JsonResponse
    {
        $user = User::query()->where('username', strtolower($data['username']))->where('is_active', true)->first();
        if (! $user || ! Hash::check($data['password'], $user->password)) {
            throw ValidationException::withMessages(['username' => 'Username atau password tidak cocok.']);
        }
        if (! $user->hasOutletAccess()) {
            throw ValidationException::withMessages(['username' => $user->outlet?->status === 'DEMO'
                ? 'Masa demo outlet telah berakhir. Hubungi admin untuk mengaktifkan akses setelah pembayaran.'
                : 'Akses outlet ini sedang nonaktif. Hubungi admin untuk informasi lebih lanjut.']);
        }

        return response()->json([
            'data' => [
                'token' => $user->createToken($deviceName)->plainTextToken,
                'token_type' => 'Bearer',
                'user' => $this->userData($user),
            ],
        ], $status);
    }

    public function me(Request $request): JsonResponse
    {
        return response()->json(['data' => ['user' => $this->userData($request->user())]]);
    }

    public function logout(Request $request): JsonResponse
    {
        $token = $request->user()->currentAccessToken();
        if ($token instanceof PersonalAccessToken) {
            $token->delete();
        }

        return response()->json(['message' => 'Berhasil keluar.']);
    }

    private function userData(User $user): array
    {
        return ['id' => $user->id, 'name' => $user->name, 'username' => $user->username, 'role' => $user->role, 'outlet_id' => $user->outlet_id, 'outlet' => $user->outlet?->only(['id', 'nama', 'status', 'logo_path', 'updated_at'])];
    }
}
