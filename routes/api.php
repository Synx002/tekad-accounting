<?php

use App\Http\Controllers\Auth\AuthController;
use Illuminate\Support\Facades\Route;

Route::prefix('v1')->group(function (): void {
    Route::post('/register', [AuthController::class, 'register'])->middleware('guest');
    Route::post('/login', [AuthController::class, 'login'])->middleware('guest');

    Route::middleware('auth:sanctum')->group(function (): void {
        Route::get('/me', [AuthController::class, 'me']);
        Route::post('/logout', [AuthController::class, 'logout']);

        Route::prefix('invoice')->middleware('permission:invoice.view')->group(function (): void {
            Route::get('/', fn () => response()->json(['module' => 'invoice', 'status' => 'ok']));
        });

        Route::prefix('purchasing')->middleware('permission:purchasing.view')->group(function (): void {
            Route::get('/', fn () => response()->json(['module' => 'purchasing', 'status' => 'ok']));
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
