<?php

use App\Http\Controllers\Auth\AuthController;
use App\Http\Controllers\InvoiceController;
use App\Http\Controllers\FixedAssetController;
use App\Http\Controllers\InventoryItemController;
use App\Http\Controllers\PurchaseBillController;
use App\Http\Controllers\ReportController;
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

        Route::prefix('inventory-items')->group(function (): void {
            Route::get('/', [InventoryItemController::class, 'index'])->middleware('permission:inventory.view');
            Route::post('/', [InventoryItemController::class, 'store'])->middleware('permission:inventory.create');
            Route::get('/{inventoryItem}/movements', [InventoryItemController::class, 'movementsIndex'])->middleware('permission:inventory.view');
            Route::post('/{inventoryItem}/movements', [InventoryItemController::class, 'movementsStore'])->middleware('permission:inventory.update');
            Route::get('/{inventoryItem}', [InventoryItemController::class, 'show'])->middleware('permission:inventory.view');
            Route::put('/{inventoryItem}', [InventoryItemController::class, 'update'])->middleware('permission:inventory.update');
            Route::delete('/{inventoryItem}', [InventoryItemController::class, 'destroy'])->middleware('permission:inventory.delete');
        });

        Route::prefix('fixed-assets')->group(function (): void {
            Route::get('/', [FixedAssetController::class, 'index'])->middleware('permission:fixed-asset.view');
            Route::post('/', [FixedAssetController::class, 'store'])->middleware('permission:fixed-asset.create');
            Route::get('/{fixedAsset}/depreciations', [FixedAssetController::class, 'depreciationsIndex'])->middleware('permission:fixed-asset.view');
            Route::post('/{fixedAsset}/depreciations', [FixedAssetController::class, 'depreciationsStore'])->middleware('permission:fixed-asset.update');
            Route::get('/{fixedAsset}', [FixedAssetController::class, 'show'])->middleware('permission:fixed-asset.view');
            Route::put('/{fixedAsset}', [FixedAssetController::class, 'update'])->middleware('permission:fixed-asset.update');
            Route::delete('/{fixedAsset}', [FixedAssetController::class, 'destroy'])->middleware('permission:fixed-asset.delete');
        });

        Route::prefix('reports')->group(function (): void {
            Route::get('/financial', [ReportController::class, 'financial'])
                ->middleware('permission:report.financial.view');
            Route::get('/business', [ReportController::class, 'business'])
                ->middleware('permission:report.business.view');
        });
    });
});
