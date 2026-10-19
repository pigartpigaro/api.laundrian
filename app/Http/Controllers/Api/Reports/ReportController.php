<?php

namespace App\Http\Controllers\Api\Reports;

use App\Http\Controllers\Controller;
use App\Models\Expense;
use App\Models\LaundryTransaction;
use App\Models\Payment;
use App\Models\TransactionDetail;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Validation\ValidationException;

class ReportController extends Controller
{
    public function sales(Request $request): JsonResponse
    {
        [$from, $to] = $this->range($request);
        $transactions = LaundryTransaction::query()->where('status_transaksi', 'AKTIF')->whereBetween('tanggal', [$from, $to]);

        return response()->json(['data' => [
            'total' => (float) (clone $transactions)->sum('total'),
            'jumlah' => (clone $transactions)->count(),
            'rata_rata' => (float) ((clone $transactions)->avg('total') ?? 0),
            'per_transaksi' => (clone $transactions)->select([
                'id', 'notrans', 'tanggal', 'nama_pelanggan', 'status_laundry', 'status_bayar', 'total',
            ])->orderByDesc('tanggal')->orderByDesc('id')->get(),
            'per_hari' => (clone $transactions)->selectRaw('DATE(tanggal) as tanggal, SUM(total) as total, COUNT(*) as jumlah')
                ->groupByRaw('DATE(tanggal)')->orderBy('tanggal')->get(),
        ], 'filters' => compact('from', 'to')]);
    }

    public function payments(Request $request): JsonResponse
    {
        [$from, $to] = $this->range($request);
        $payments = Payment::query()->whereNull('void_at')->whereBetween('tanggal', [$from, $to]);

        return response()->json(['data' => [
            'total' => (float) (clone $payments)->sum('nominal'),
            'per_metode' => (clone $payments)->select('metode')->selectRaw('SUM(nominal) as total, COUNT(*) as jumlah')->groupBy('metode')->get(),
            'per_hari' => (clone $payments)->selectRaw('DATE(tanggal) as tanggal, SUM(nominal) as total')->groupByRaw('DATE(tanggal)')->orderBy('tanggal')->get(),
            'items' => (clone $payments)->with(['transaction:id,notrans,nama_pelanggan', 'user:id,name'])
                ->orderByDesc('tanggal')->orderByDesc('id')->get(),
        ], 'filters' => compact('from', 'to')]);
    }

    public function receivables(Request $request): JsonResponse
    {
        $filters = $request->validate(['q' => ['nullable', 'string', 'max:100'], 'per_page' => ['nullable', 'integer', 'min:1', 'max:100']]);
        $transactions = LaundryTransaction::query()->with('customer:id,kode,nama,no_hp')->where('status_transaksi', 'AKTIF')->where('sisa', '>', 0)
            ->when($filters['q'] ?? null, fn ($query, $term) => $query->where(fn ($q) => $q->where('notrans', 'like', "%{$term}%")->orWhere('nama_pelanggan', 'like', "%{$term}%")))
            ->orderByDesc('sisa')->orderBy('tanggal')->cursorPaginate($filters['per_page'] ?? 30);

        return response()->json(['total_piutang' => (float) LaundryTransaction::query()->where('status_transaksi', 'AKTIF')->sum('sisa'), 'transaksi' => $transactions]);
    }

    public function expenses(Request $request): JsonResponse
    {
        [$from, $to] = $this->range($request);
        $expenses = Expense::query()->whereBetween('tanggal', [$from->toDateString(), $to->toDateString()]);

        return response()->json(['data' => [
            'total' => (float) (clone $expenses)->sum('nominal'),
            'per_kategori' => (clone $expenses)->select('kategori')->selectRaw('SUM(nominal) as total, COUNT(*) as jumlah')->groupBy('kategori')->orderByDesc('total')->get(),
            'items' => (clone $expenses)->with('user:id,name')->orderByDesc('tanggal')->orderByDesc('id')->get(),
        ], 'filters' => compact('from', 'to')]);
    }

    public function profit(Request $request): JsonResponse
    {
        [$from, $to] = $this->range($request);
        $incomeTransactions = LaundryTransaction::query()->where('status_transaksi', 'AKTIF')->whereBetween('tanggal', [$from, $to]);
        $expenseItems = Expense::query()->whereBetween('tanggal', [$from->toDateString(), $to->toDateString()]);
        $income = (clone $incomeTransactions)->sum('total');
        $expenses = (clone $expenseItems)->sum('nominal');

        return response()->json(['data' => [
            'pendapatan' => (float) $income,
            'pengeluaran' => (float) $expenses,
            'laba_bersih' => (float) $income - (float) $expenses,
            'transaksi' => (clone $incomeTransactions)->select([
                'id', 'notrans', 'tanggal', 'nama_pelanggan', 'total',
            ])->orderByDesc('tanggal')->orderByDesc('id')->get(),
            'items_pengeluaran' => (clone $expenseItems)->orderByDesc('tanggal')->orderByDesc('id')->get(),
        ], 'filters' => compact('from', 'to')]);
    }

    public function popularServices(Request $request): JsonResponse
    {
        [$from, $to] = $this->range($request);
        $services = TransactionDetail::query()->join('transaksis', 'transaksi_rincians.transaksi_id', '=', 'transaksis.id')
            ->where('transaksis.status_transaksi', 'AKTIF')->whereBetween('transaksis.tanggal', [$from, $to])
            ->where('transaksis.outlet_id', $request->user()->role === 'SUPERADMIN' ? $request->header('X-Outlet-ID') : $request->user()->outlet_id)
            ->select('transaksi_rincians.nama_layanan', 'transaksi_rincians.satuan')
            ->selectRaw('SUM(transaksi_rincians.qty) as total_qty, SUM(transaksi_rincians.subtotal) as total_penjualan, COUNT(DISTINCT transaksis.id) as jumlah_transaksi')
            ->groupBy('transaksi_rincians.nama_layanan', 'transaksi_rincians.satuan')->orderByDesc('total_qty')->limit(20)->get();

        return response()->json(['data' => $services, 'filters' => compact('from', 'to')]);
    }

    private function range(Request $request): array
    {
        $data = $request->validate([
            'from' => ['nullable', 'date'],
            'to' => ['nullable', 'date'],
        ]);
        if (isset($data['from'], $data['to']) && Carbon::parse($data['to'])->lt(Carbon::parse($data['from']))) {
            throw ValidationException::withMessages(['to' => 'Tanggal akhir harus sama atau setelah tanggal awal.']);
        }

        return [
            isset($data['from']) ? Carbon::parse($data['from'])->startOfDay() : now()->startOfMonth(),
            isset($data['to']) ? Carbon::parse($data['to'])->endOfDay() : now()->endOfDay(),
        ];
    }
}
