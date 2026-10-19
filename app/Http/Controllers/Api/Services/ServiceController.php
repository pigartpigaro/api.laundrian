<?php

namespace App\Http\Controllers\Api\Services;

use App\Http\Controllers\Controller;
use App\Models\Service;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;

class ServiceController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $services = Service::query()
            ->when(! $request->user()->isOwner(), fn ($query) => $query->where('aktif', true))
            ->orderBy('nama')->get();

        return response()->json(['data' => $services]);
    }

    public function store(Request $request): JsonResponse
    {
        $data = $this->validated($request);
        $service = Service::query()->create(['kode' => 'SRV-'.Str::upper(Str::random(10))] + $data);

        return response()->json(['data' => $service], 201);
    }

    public function update(Request $request, Service $service): JsonResponse
    {
        $service->update($this->validated($request, $service));

        return response()->json(['data' => $service->refresh()]);
    }

    private function validated(Request $request, ?Service $service = null): array
    {
        return $request->validate([
            'nama' => ['required', 'string', 'max:120'],
            'jenis_hitung' => ['required', Rule::in(['KG', 'PCS', 'PASANG', 'METER'])],
            'harga' => ['required', 'numeric', 'gt:0', 'max:9999999999'],
            'estimasi_jam' => ['required', 'integer', 'min:72', 'max:720'],
            'aktif' => ['sometimes', 'boolean'],
        ]);
    }
}
