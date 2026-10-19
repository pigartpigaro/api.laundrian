<?php

namespace App\Http\Controllers\Api\Settings;

use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Password;
use Illuminate\Validation\ValidationException;

class UserController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        return response()->json($this->outletUsers($request)
            ->select(['id', 'outlet_id', 'name', 'username', 'email', 'role', 'is_active', 'created_at'])
            ->orderBy('name')->orderBy('id')->cursorPaginate(30));
    }

    public function store(Request $request): JsonResponse
    {
        $request->merge(['username' => strtolower(trim((string) $request->input('username')))]);
        $data = $request->validate([
            'name' => ['required', 'string', 'max:120'],
            'username' => ['required', 'string', 'min:3', 'max:40', 'alpha_dash:ascii', 'unique:users,username'],
            'email' => ['nullable', 'email', 'max:190', 'unique:users,email'],
            'password' => ['required', 'confirmed', Password::min(8)],
        ]);
        $data['username'] = strtolower($data['username']);
        $data['password'] = Hash::make($data['password']);
        $data['role'] = 'KASIR';
        $data['is_active'] = true;
        $data['outlet_id'] = $this->outletId($request);

        abort_unless($data['outlet_id'], 422, 'Outlet aktif belum dipilih.');
        $user = User::query()->create($data);

        return response()->json(['data' => $user->only(['id', 'outlet_id', 'name', 'username', 'email', 'role', 'is_active', 'created_at'])], 201);
    }

    public function update(Request $request, User $user): JsonResponse
    {
        abort_unless($user->outlet_id === $this->outletId($request), 404);
        abort_if($user->role === 'SUPERADMIN', 403, 'Akun superadmin tidak dikelola dari menu outlet.');
        if ($request->exists('username')) $request->merge(['username' => strtolower(trim((string) $request->input('username')))]);

        $data = $request->validate([
            'name' => ['sometimes', 'required', 'string', 'max:120'],
            'username' => ['sometimes', 'required', 'string', 'min:3', 'max:40', 'alpha_dash:ascii', Rule::unique('users', 'username')->ignore($user->id)],
            'email' => ['sometimes', 'nullable', 'email', 'max:190', Rule::unique('users', 'email')->ignore($user->id)],
            'password' => ['sometimes', 'nullable', 'confirmed', Password::min(8)],
            'role' => ['sometimes', Rule::in($request->user()->role === 'SUPERADMIN' ? ['OWNER', 'KASIR'] : ['KASIR'])],
            'is_active' => ['sometimes', 'boolean'],
        ]);

        $requestedRole = $data['role'] ?? null;
        return DB::transaction(function () use ($request, $user, $data, $requestedRole): JsonResponse {
            $locked = User::query()->whereKey($user->id)->lockForUpdate()->firstOrFail();
            $nextRole = $data['role'] ?? $locked->role;
            $nextActive = $data['is_active'] ?? $locked->is_active;
            if ($locked->role === 'OWNER' && ($nextRole !== 'OWNER' || ! $nextActive)) {
                if ($request->user()->is($locked)) throw ValidationException::withMessages(['user' => 'Owner tidak dapat menonaktifkan atau menurunkan role akunnya sendiri.']);
                $owners = User::query()->where('outlet_id', $locked->outlet_id)->where('role', 'OWNER')->where('is_active', true)->lockForUpdate()->count();
                if ($owners <= 1) throw ValidationException::withMessages(['role' => 'Setidaknya satu owner aktif harus dipertahankan pada outlet ini.']);
            }

            if (isset($data['password']) && $data['password'] !== '') $data['password'] = Hash::make($data['password']);
            else unset($data['password']);
            unset($data['role']);
            if ($request->user()->role === 'SUPERADMIN' && $requestedRole) $data['role'] = $requestedRole;
            $locked->update($data);
            if (isset($data['password']) || (array_key_exists('is_active', $data) && ! $data['is_active'])) $locked->tokens()->delete();

            return response()->json(['data' => $locked->refresh()->only(['id', 'outlet_id', 'name', 'username', 'email', 'role', 'is_active', 'created_at'])]);
        });
    }

    private function outletUsers(Request $request): \Illuminate\Database\Eloquent\Builder
    {
        return User::query()->where('outlet_id', $this->outletId($request));
    }

    private function outletId(Request $request): ?int
    {
        $id = $request->user()->role === 'SUPERADMIN' ? $request->header('X-Outlet-ID') : $request->user()->outlet_id;
        return $id ? (int) $id : null;
    }
}
