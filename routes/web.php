<?php

use App\Http\Controllers\BranchController;
use App\Http\Controllers\Logistics\FleetController;
use App\Http\Controllers\Notifications\PushSubscriptionController;
use App\Http\Controllers\Purchasing\GmailOAuthController;
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

    // Gmail OAuth — conectar correo de compras (no requiere branch, es config global)
    Route::prefix('purchasing/gmail')->name('purchasing.gmail.')->group(function () {
        Route::get('connect', [GmailOAuthController::class, 'connect'])->name('connect');
        Route::get('callback', [GmailOAuthController::class, 'callback'])->name('callback');
    });

    // Web Push — suscripciones del dispositivo del usuario
    // No requiere branch.required: la suscripción es por usuario, no por sucursal
    Route::post('push/subscriptions', [PushSubscriptionController::class, 'store'])
        ->name('push.subscriptions.store');
    Route::delete('push/subscriptions', [PushSubscriptionController::class, 'destroy'])
        ->name('push.subscriptions.destroy');
    Route::post('push/test', [PushSubscriptionController::class, 'testNotification'])
        ->name('push.test');
});

Route::middleware(['auth', 'verified', 'branch.required'])->group(function () {
    Route::inertia('dashboard', 'dashboard')->name('dashboard');

    Route::prefix('inventory')->name('inventory.')->group(function () {
        Route::inertia('products', 'inventory/products/index')->name('products.index');
        Route::inertia('transfers', 'inventory/transfers/index')->name('transfers.index');
    });

    Route::prefix('compras')->name('purchasing.')->group(function () {
        Route::inertia('/', 'purchasing/index')->name('index');
        Route::inertia('recepcion', 'purchasing/receipt/index')->name('receipt.index');
    });

    Route::prefix('equipo')->name('team.')->group(function () {
        Route::get('empleados', [App\Http\Controllers\Team\EmployeeController::class, 'index'])->name('employees.index');
        Route::put('empleados/{user}/branches', [App\Http\Controllers\Team\EmployeeController::class, 'updateBranches'])->name('employees.branches.update');
        Route::put('empleados/{user}/roles', [App\Http\Controllers\Team\EmployeeController::class, 'updateRoles'])->name('employees.roles.update');
    });

    Route::prefix('logistica')->name('logistics.')->group(function () {
        Route::inertia('/', 'logistics/index')->name('index');
        Route::post('fleet/refresh', [FleetController::class, 'refresh'])->name('fleet.refresh');
        Route::post('fleet/alert-test', [FleetController::class, 'testFleetAlerts'])->name('fleet.alert-test');
    });
});

require __DIR__.'/settings.php';
