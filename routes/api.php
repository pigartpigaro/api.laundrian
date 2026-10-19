<?php

use App\Http\Controllers\Api\Auth\AuthController;
use App\Http\Controllers\Api\Auth\RegistrationController;
use App\Http\Controllers\Api\Customers\CustomerController;
use App\Http\Controllers\Api\Dashboard\DashboardController;
use App\Http\Controllers\Api\Expenses\ExpenseController;
use App\Http\Controllers\Api\Reports\ReportController;
use App\Http\Controllers\Api\Services\ServiceController;
use App\Http\Controllers\Api\Settings\SettingController;
use App\Http\Controllers\Api\Settings\UserController;
use App\Http\Controllers\Api\Transactions\PaymentController;
use App\Http\Controllers\Api\Transactions\TransactionController;
use App\Http\Controllers\Api\Transactions\TransactionStatusController;
use App\Http\Controllers\Api\Superadmin\OutletController;
use Illuminate\Support\Facades\Route;

Route::post('/login', [AuthController::class, 'login'])->middleware('throttle:login');
Route::post('/register', [RegistrationController::class, 'store'])->middleware('throttle:register');
Route::get('/public/outlets/{outlet}/logo', [SettingController::class, 'logo'])->whereNumber('outlet');
Route::get('/public/outlets/{outlet}/website', [SettingController::class, 'publicWebsite'])->whereNumber('outlet');
Route::get('/public/outlets/{outlet}/website-media/{filename}', [SettingController::class, 'websiteMedia'])->whereNumber('outlet')->where('filename', '[A-Za-z0-9._-]+');
    Route::post('/token', [AuthController::class, 'token'])->middleware('throttle:login');
Route::post('/logout', [AuthController::class, 'logout'])->middleware('auth:sanctum');

    Route::middleware(['auth:sanctum', 'active'])->group(function (): void {
        Route::get('/me', [AuthController::class, 'me']);

        Route::prefix('superadmin')->middleware('superadmin')->group(function (): void {
            Route::get('/outlets', [OutletController::class, 'index']);
            Route::put('/outlets/{outlet}', [OutletController::class, 'update']);
        });

        Route::get('/dashboard', DashboardController::class);

        Route::get('/layanan', [ServiceController::class, 'index']);
        Route::post('/layanan', [ServiceController::class, 'store'])->middleware('owner');
        Route::put('/layanan/{service}', [ServiceController::class, 'update'])->middleware('owner');

        Route::get('/pelanggan', [CustomerController::class, 'index']);
        Route::post('/pelanggan', [CustomerController::class, 'store']);
        Route::put('/pelanggan/{customer}', [CustomerController::class, 'update']);

        Route::get('/transaksi', [TransactionController::class, 'index']);
        Route::post('/transaksi', [TransactionController::class, 'store']);
        Route::get('/transaksi/{transaction}', [TransactionController::class, 'show']);
        Route::post('/transaksi/{transaction}/proses', [TransactionStatusController::class, 'process']);
        Route::post('/transaksi/{transaction}/selesai', [TransactionStatusController::class, 'complete']);
        Route::post('/transaksi/{transaction}/diambil', [TransactionStatusController::class, 'pickup']);
        Route::post('/transaksi/{transaction}/batal', [TransactionStatusController::class, 'cancel'])->middleware('owner');
        Route::post('/transaksi/{transaction}/koreksi-status', [TransactionStatusController::class, 'correct'])->middleware('owner');
        Route::post('/transaksi/{transaction}/pembayaran', [PaymentController::class, 'store']);

        Route::post('/pembayaran/{payment}/void', [PaymentController::class, 'void'])->middleware('owner');

        Route::get('/pengeluaran', [ExpenseController::class, 'index']);
        Route::post('/pengeluaran', [ExpenseController::class, 'store']);

        Route::prefix('laporan')->middleware('owner')->group(function (): void {
            Route::get('/penjualan', [ReportController::class, 'sales']);
            Route::get('/pembayaran', [ReportController::class, 'payments']);
            Route::get('/piutang', [ReportController::class, 'receivables']);
            Route::get('/pengeluaran', [ReportController::class, 'expenses']);
            Route::get('/laba-rugi', [ReportController::class, 'profit']);
            Route::get('/layanan-terlaris', [ReportController::class, 'popularServices']);
        });

        Route::middleware('owner')->group(function (): void {
            Route::get('/pengaturan', [SettingController::class, 'show']);
            Route::put('/pengaturan', [SettingController::class, 'update']);
            Route::get('/karyawan', [UserController::class, 'index']);
            Route::post('/karyawan', [UserController::class, 'store']);
            Route::put('/karyawan/{user}', [UserController::class, 'update']);
        });
    });
