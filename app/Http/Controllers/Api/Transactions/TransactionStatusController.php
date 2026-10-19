<?php

namespace App\Http\Controllers\Api\Transactions;

use App\Http\Controllers\Controller;
use App\Models\LaundryTransaction;
use App\Models\TransactionStatusHistory;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

class TransactionStatusController extends Controller
{
    public function process(Request $request, LaundryTransaction $transaction): JsonResponse
    {
        return $this->transition($request, $transaction, 'MASUK', 'PROSES');
    }

    public function complete(Request $request, LaundryTransaction $transaction): JsonResponse
    {
        return $this->transition($request, $transaction, 'PROSES', 'SELESAI');
    }

    public function pickup(Request $request, LaundryTransaction $transaction): JsonResponse
    {
        if ((float) $transaction->sisa > 0) {
            throw ValidationException::withMessages(['pembayaran' => 'Transaksi harus lunas sebelum laundry diserahkan.']);
        }

        return $this->transition($request, $transaction, 'SELESAI', 'DIAMBIL');
    }

    public function cancel(Request $request, LaundryTransaction $transaction): JsonResponse
    {
        $data = $request->validate(['alasan' => ['required', 'string', 'min:5', 'max:1000']]);

        return DB::transaction(function () use ($request, $transaction, $data): JsonResponse {
            $locked = LaundryTransaction::query()->whereKey($transaction->id)->lockForUpdate()->firstOrFail();
            if ($locked->status_transaksi !== 'AKTIF' || $locked->status_laundry === 'DIAMBIL') {
                throw ValidationException::withMessages(['transaksi' => 'Transaksi ini tidak dapat dibatalkan.']);
            }
            $locked->update([
                'status_transaksi' => 'BATAL',
                'dibatalkan_oleh' => $request->user()->id,
                'dibatalkan_pada' => now(),
                'alasan_batal' => $data['alasan'],
            ]);
            $locked->statusHistory()->create([
                'jenis' => 'TRANSAKSI', 'status_dari' => 'AKTIF', 'status_ke' => 'BATAL',
                'alasan' => $data['alasan'], 'user_id' => $request->user()->id,
            ]);

            return response()->json(['data' => $locked->load(['customer', 'details', 'payments', 'statusHistory.user:id,name'])]);
        });
    }

    public function correct(Request $request, LaundryTransaction $transaction): JsonResponse
    {
        $data = $request->validate([
            'status_laundry' => ['required', Rule::in(['MASUK', 'PROSES', 'SELESAI', 'DIAMBIL'])],
            'alasan' => ['required', 'string', 'min:5', 'max:1000'],
        ]);

        return $this->setStatus($request, $transaction, $data['status_laundry'], $data['alasan']);
    }

    private function transition(Request $request, LaundryTransaction $transaction, string $from, string $to): JsonResponse
    {
        if ($transaction->status_laundry !== $from) {
            throw ValidationException::withMessages(['status_laundry' => "Status harus {$from} sebelum diubah menjadi {$to}."]);
        }

        return $this->setStatus($request, $transaction, $to, null, $from);
    }

    private function setStatus(Request $request, LaundryTransaction $transaction, string $to, ?string $reason, ?string $expectedFrom = null): JsonResponse
    {
        return DB::transaction(function () use ($request, $transaction, $to, $reason, $expectedFrom): JsonResponse {
            $locked = LaundryTransaction::query()->whereKey($transaction->id)->lockForUpdate()->firstOrFail();
            if ($locked->status_transaksi !== 'AKTIF') {
                throw ValidationException::withMessages(['transaksi' => 'Transaksi yang dibatalkan tidak dapat diubah.']);
            }
            if ($expectedFrom && $locked->status_laundry !== $expectedFrom) {
                throw ValidationException::withMessages(['status_laundry' => 'Status berubah. Muat ulang transaksi lalu coba lagi.']);
            }
            if ($to === 'DIAMBIL' && (float) $locked->sisa > 0) {
                throw ValidationException::withMessages(['pembayaran' => 'Transaksi harus lunas sebelum laundry diserahkan.']);
            }

            $from = $locked->status_laundry;
            $locked->update(['status_laundry' => $to]);
            $locked->statusHistory()->create([
                'jenis' => 'LAUNDRY', 'status_dari' => $from, 'status_ke' => $to,
                'alasan' => $reason, 'user_id' => $request->user()->id,
            ]);

            return response()->json(['data' => $locked->load(['customer', 'cashier:id,name', 'details.service', 'payments.user:id,name', 'statusHistory.user:id,name'])]);
        });
    }
}
