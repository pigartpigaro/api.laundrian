<?php

namespace App\Http\Controllers\Api\Superadmin;

use App\Http\Controllers\Controller;
use App\Models\Outlet;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class OutletController extends Controller
{
    public function index(): JsonResponse
    {
        return response()->json(['data' => Outlet::query()->withCount('users')->orderByDesc('created_at')->get()]);
    }

    public function update(Request $request, Outlet $outlet): JsonResponse
    {
        $data = $request->validate(['status' => ['required', Rule::in(['AKTIF', 'NONAKTIF'])]]);
        $outlet->update($data);

        return response()->json(['data' => $outlet->refresh()]);
    }
}
