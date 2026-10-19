<?php

namespace App\Http\Controllers\Api\Dashboard;

use App\Http\Controllers\Controller;
use App\Models\Expense;
use App\Models\LaundryTransaction;
use Illuminate\Http\JsonResponse;

class DashboardController extends Controller
{
    public function __invoke(): JsonResponse
    {
        $today = today();
        $active = LaundryTransaction::query()->where('status_transaksi', 'AKTIF');
        $todayTransactions = (clone $active)->whereDate('tanggal', $today);

        return response()->json(['data' => [
            'tanggal' => $today->toDateString(),
            'ringkasan' => [
                'omzet_hari_ini' => (float) (clone $todayTransactions)->sum('total'),
                'pembayaran_hari_ini' => (float) \App\Models\Payment::query()->whereNull('void_at')->whereDate('tanggal', $today)->sum('nominal'),
                'jumlah_transaksi_hari_ini' => (clone $todayTransactions)->count(),
                'piutang_aktif' => (float) (clone $active)->sum('sisa'),
                'jumlah_pengeluaran_hari_ini' => (float) Expense::query()->whereDate('tanggal', $today)->sum('nominal'),
            ],
            'pekerjaan' => [
                'MASUK' => (clone $active)->where('status_laundry', 'MASUK')->count(),
                'PROSES' => (clone $active)->where('status_laundry', 'PROSES')->count(),
                'SELESAI' => (clone $active)->where('status_laundry', 'SELESAI')->count(),
                'DIAMBIL' => (clone $active)->where('status_laundry', 'DIAMBIL')->count(),
            ],
            'terlambat' => (clone $active)->whereIn('status_laundry', ['MASUK', 'PROSES'])
                ->whereNotNull('estimasi_selesai')->where('estimasi_selesai', '<', now())->count(),
        ]]);
    }
}
