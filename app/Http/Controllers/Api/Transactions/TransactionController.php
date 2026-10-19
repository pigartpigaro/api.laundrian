<?php

namespace App\Http\Controllers\Api\Transactions;

use App\Http\Controllers\Controller;
use App\Models\LaundryTransaction;
use App\Services\Transactions\TransactionService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class TransactionController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $filters = $request->validate([
            'q' => ['nullable', 'string', 'max:100'],
            'status_laundry' => ['nullable', Rule::in(['MASUK', 'PROSES', 'SELESAI', 'DIAMBIL'])],
            'status_bayar' => ['nullable', Rule::in(['BELUM BAYAR', 'DP', 'LUNAS'])],
            'status_transaksi' => ['nullable', Rule::in(['AKTIF', 'BATAL', 'SEMUA'])],
            'from' => ['nullable', 'date'],
            'to' => ['nullable', 'date', 'after_or_equal:from'],
            'per_page' => ['nullable', 'integer', 'min:1', 'max:100'],
        ]);

        $transactions = LaundryTransaction::query()->with(['customer:id,kode,nama,no_hp', 'cashier:id,name', 'details', 'payments'])
            ->when(($filters['status_transaksi'] ?? 'AKTIF') !== 'SEMUA', fn ($query) => $query->where('status_transaksi', $filters['status_transaksi'] ?? 'AKTIF'))
            ->when($filters['status_laundry'] ?? null, fn ($query, $status) => $query->where('status_laundry', $status))
            ->when($filters['status_bayar'] ?? null, fn ($query, $status) => $query->where('status_bayar', $status))
            ->when($filters['from'] ?? null, fn ($query, $date) => $query->whereDate('tanggal', '>=', $date))
            ->when($filters['to'] ?? null, fn ($query, $date) => $query->whereDate('tanggal', '<=', $date))
            ->when($filters['q'] ?? null, fn ($query, $term) => $query->where(function ($q) use ($term): void {
                $q->where('notrans', 'like', "%{$term}%")->orWhere('nama_pelanggan', 'like', "%{$term}%")->orWhere('no_hp', 'like', "%{$term}%");
            }))
            ->orderByDesc('tanggal')->orderByDesc('id')->cursorPaginate($filters['per_page'] ?? 30);

        return response()->json($transactions);
    }

    public function store(Request $request, TransactionService $transactions): JsonResponse
    {
        $data = $request->validate([
            'pelanggan_id' => ['nullable', 'integer', 'exists:pelanggans,id'],
            'nama_pelanggan' => ['required_without:pelanggan_id', 'nullable', 'string', 'max:150'],
            'no_hp' => ['nullable', 'string', 'max:32'],
            'rincian' => ['required', 'array', 'min:1', 'max:30'],
            'rincian.*.layanan_id' => ['required', 'integer', 'exists:layanans,id'],
            'rincian.*.qty' => ['required', 'numeric', 'gt:0', 'max:99999'],
            'rincian.*.catatan' => ['nullable', 'string', 'max:1000'],
            'diskon' => ['sometimes', 'numeric', 'min:0', 'max:9999999999'],
            'ongkir' => ['sometimes', 'numeric', 'min:0', 'max:9999999999'],
            'catatan' => ['nullable', 'string', 'max:2000'],
            'pembayaran_awal' => ['prohibited'],
        ]);

        $transaction = $transactions->create($request->user(), $data);

        return response()->json(['data' => $transaction], 201);
    }

    public function show(LaundryTransaction $transaction): JsonResponse
    {
        return response()->json(['data' => $transaction->load(['customer', 'cashier:id,name', 'details.service', 'payments.user:id,name', 'statusHistory.user:id,name'])]);
    }
}
