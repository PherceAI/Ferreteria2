<?php

use App\Http\Controllers\BranchController;
use App\Http\Controllers\Notifications\PushSubscriptionController;
use Illuminate\Support\Facades\Route;
use Laravel\Fortify\Features;

Route::inertia('/', 'welcome', [
    'canRegister' => Features::enabled(Features::registration()),
])->name('home');

Route::middleware(['auth', 'verified'])->group(function () {
    // Sin sucursal asignada — página de aviso (no requiere branch.required)
    Route::inertia('sin-sucursal', 'branch-required')->name('branch.required');

    Route::put('branch/switch', [BranchController::class, 'switch'])
        ->name('branch.switch');

    // Web Push — suscripciones del dispositivo del usuario
    // No requiere branch.required: la suscripción es por usuario, no por sucursal
    Route::post('push/subscriptions', [PushSubscriptionController::class, 'store'])
        ->name('push.subscriptions.store');
    Route::delete('push/subscriptions', [PushSubscriptionController::class, 'destroy'])
        ->name('push.subscriptions.destroy');
});

Route::middleware(['auth', 'verified', 'branch.required'])->group(function () {
    Route::inertia('dashboard', 'dashboard')->name('dashboard');

    Route::prefix('inventory')->name('inventory.')->group(function () {
        Route::inertia('products', 'inventory/products/index')->name('products.index');
    });
});

require __DIR__.'/settings.php';
