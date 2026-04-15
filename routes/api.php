<?php

use App\Http\Controllers\Auth\AuthController;
use App\Http\Controllers\InvoiceController;
use App\Http\Controllers\PurchaseBillController;
use Illuminate\Support\Facades\Route;

Route::prefix('v1')->group(function (): void {
    Route::post('/register', [AuthController::class, 'register'])->middleware('guest');
    Route::post('/login', [AuthController::class, 'login'])->middleware('guest');

    Route::middleware('auth:sanctum')->group(function (): void {
        Route::get('/me', [AuthController::class, 'me']);
        Route::post('/logout', [AuthController::class, 'logout']);

        Route::prefix('invoices')->group(function (): void {
            Route::get('/', [InvoiceController::class, 'index'])->middleware('permission:invoice.view');
            Route::post('/', [InvoiceController::class, 'store'])->middleware('permission:invoice.create');
            Route::get('/{invoice}', [InvoiceController::class, 'show'])->middleware('permission:invoice.view');
            Route::put('/{invoice}', [InvoiceController::class, 'update'])->middleware('permission:invoice.update');
            Route::delete('/{invoice}', [InvoiceController::class, 'destroy'])->middleware('permission:invoice.delete');
        });

        Route::prefix('purchase-bills')->group(function (): void {
            Route::get('/', [PurchaseBillController::class, 'index'])->middleware('permission:purchasing.view');
            Route::post('/', [PurchaseBillController::class, 'store'])->middleware('permission:purchasing.create');
            Route::get('/{purchaseBill}', [PurchaseBillController::class, 'show'])->middleware('permission:purchasing.view');
            Route::put('/{purchaseBill}', [PurchaseBillController::class, 'update'])->middleware('permission:purchasing.update');
            Route::delete('/{purchaseBill}', [PurchaseBillController::class, 'destroy'])->middleware('permission:purchasing.delete');
        });

        Route::prefix('inventory')->middleware('permission:inventory.view')->group(function (): void {
            Route::get('/', fn () => response()->json(['module' => 'inventory', 'status' => 'ok']));
        });

        Route::prefix('fixed-assets')->middleware('permission:fixed-asset.view')->group(function (): void {
            Route::get('/', fn () => response()->json(['module' => 'fixed-assets', 'status' => 'ok']));
        });

        Route::prefix('reports')->group(function (): void {
            Route::get('/financial', fn () => response()->json(['module' => 'reports.financial', 'status' => 'ok']))
                ->middleware('permission:report.financial.view');
            Route::get('/business', fn () => response()->json(['module' => 'reports.business', 'status' => 'ok']))
                ->middleware('permission:report.business.view');
        });
    });
});
