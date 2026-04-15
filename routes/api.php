<?php

use App\Http\Controllers\AccountController;
use App\Http\Controllers\Auth\AuthController;
use App\Http\Controllers\ExpenseController;
use App\Http\Controllers\InvoiceController;
use App\Http\Controllers\JournalEntryController;
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
            Route::post('/{invoice}/pay', [InvoiceController::class, 'pay'])->middleware('permission:invoice.update');
            Route::get('/{invoice}', [InvoiceController::class, 'show'])->middleware('permission:invoice.view');
            Route::put('/{invoice}', [InvoiceController::class, 'update'])->middleware('permission:invoice.update');
            Route::delete('/{invoice}', [InvoiceController::class, 'destroy'])->middleware('permission:invoice.delete');
        });

        Route::prefix('expenses')->group(function (): void {
            Route::get('/', [ExpenseController::class, 'index'])->middleware('permission:expense.view');
            Route::post('/', [ExpenseController::class, 'store'])->middleware('permission:expense.create');
            Route::post('/{expense}/pay', [ExpenseController::class, 'pay'])->middleware('permission:expense.update');
            Route::get('/{expense}', [ExpenseController::class, 'show'])->middleware('permission:expense.view');
            Route::put('/{expense}', [ExpenseController::class, 'update'])->middleware('permission:expense.update');
            Route::delete('/{expense}', [ExpenseController::class, 'destroy'])->middleware('permission:expense.delete');
        });

        Route::prefix('purchase-bills')->group(function (): void {
            Route::get('/', [PurchaseBillController::class, 'index'])->middleware('permission:purchasing.view');
            Route::post('/', [PurchaseBillController::class, 'store'])->middleware('permission:purchasing.create');
            Route::post('/{purchaseBill}/pay', [PurchaseBillController::class, 'pay'])->middleware('permission:purchasing.update');
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

        Route::prefix('accounts')->group(function (): void {
            Route::get('/', [AccountController::class, 'index'])->middleware('permission:ledger.account.view');
            Route::post('/', [AccountController::class, 'store'])->middleware('permission:ledger.account.manage');
            Route::get('/{account}', [AccountController::class, 'show'])->middleware('permission:ledger.account.view');
            Route::put('/{account}', [AccountController::class, 'update'])->middleware('permission:ledger.account.manage');
            Route::delete('/{account}', [AccountController::class, 'destroy'])->middleware('permission:ledger.account.manage');
        });

        Route::prefix('journal-entries')->group(function (): void {
            Route::get('/', [JournalEntryController::class, 'index'])->middleware('permission:ledger.journal.view');
            Route::post('/', [JournalEntryController::class, 'store'])->middleware('permission:ledger.journal.create');
            Route::post('/{journalEntry}/post', [JournalEntryController::class, 'post'])->middleware('permission:ledger.journal.post');
            Route::get('/{journalEntry}', [JournalEntryController::class, 'show'])->middleware('permission:ledger.journal.view');
            Route::put('/{journalEntry}', [JournalEntryController::class, 'update'])->middleware('permission:ledger.journal.create');
            Route::delete('/{journalEntry}', [JournalEntryController::class, 'destroy'])->middleware('permission:ledger.journal.delete');
        });

        Route::prefix('reports')->group(function (): void {
            Route::get('/financial', [ReportController::class, 'financial'])
                ->middleware('permission:report.financial.view');
            Route::get('/business', [ReportController::class, 'business'])
                ->middleware('permission:report.business.view');
            Route::get('/income-statement', [ReportController::class, 'incomeStatement'])
                ->middleware('permission:report.income-statement.view');
            Route::get('/balance-sheet', [ReportController::class, 'balanceSheet'])
                ->middleware('permission:report.balance-sheet.view');
        });
    });
});
