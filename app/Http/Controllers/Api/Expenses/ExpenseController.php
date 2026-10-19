<?php

namespace App\Http\Controllers\Api\Expenses;

use App\Http\Controllers\Controller;
use App\Models\Expense;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

class ExpenseController extends Controller
{
    private const CATEGORIES = ['DETERJEN', 'PEWANGI', 'PLASTIK', 'LISTRIK', 'AIR', 'TRANSPORT', 'PERAWATAN', 'GAJI', 'LAINNYA'];

    public function index(Request $request): JsonResponse
    {
        $filters = $request->validate([
            'from' => ['nullable', 'date'],
            'to' => ['nullable', 'date'],
            'per_page' => ['nullable', 'integer', 'min:1', 'max:100'],
        ]);
        if (isset($filters['from'], $filters['to']) && $filters['to'] < $filters['from']) {
            throw ValidationException::withMessages(['to' => 'Tanggal akhir harus sama atau setelah tanggal awal.']);
        }
        $expenses = Expense::query()->with('user:id,name')
            ->when($filters['from'] ?? null, fn ($query, $date) => $query->whereDate('tanggal', '>=', $date))
            ->when($filters['to'] ?? null, fn ($query, $date) => $query->whereDate('tanggal', '<=', $date))
            ->orderByDesc('tanggal')->orderByDesc('id')->cursorPaginate($filters['per_page'] ?? 30);

        return response()->json($expenses);
    }

    public function store(Request $request): JsonResponse
    {
        $data = $request->validate([
            'tanggal' => ['nullable', 'date'],
            'kategori' => ['required', Rule::in(self::CATEGORIES)],
            'keterangan' => ['required', 'string', 'max:255'],
            'nominal' => ['required', 'numeric', 'gt:0', 'max:9999999999'],
        ]);
        $data['tanggal'] = $data['tanggal'] ?? today();
        $data['user_id'] = $request->user()->id;
        $expense = Expense::query()->create($data);

        return response()->json(['data' => $expense->load('user:id,name')], 201);
    }
}
