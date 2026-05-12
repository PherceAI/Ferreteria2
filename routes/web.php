<?php

use App\Http\Controllers\BranchController;
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\Inventory\BranchTransferController;
use App\Http\Controllers\Inventory\InventoryAlertSettingsController;
use App\Http\Controllers\Inventory\InventoryProductController;
use App\Http\Controllers\Logistics\FleetController;
use App\Http\Controllers\Notifications\PushSubscriptionController;
use App\Http\Controllers\Purchasing\GmailOAuthController;
use App\Http\Controllers\Purchasing\PurchasingController;
use App\Http\Controllers\Purchasing\PurchasingReceiptController;
use App\Http\Controllers\Team\EmployeeController;
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
    Route::get('dashboard', DashboardController::class)->name('dashboard');

    Route::prefix('inventory')->name('inventory.')->group(function () {
        Route::get('products', [InventoryProductController::class, 'index'])->name('products.index');
        Route::get('alerts', [InventoryAlertSettingsController::class, 'index'])->name('alerts.index');
        Route::post('alerts/settings', [InventoryAlertSettingsController::class, 'store'])->name('alerts.settings.store');
        Route::get('transfers', [BranchTransferController::class, 'index'])->name('transfers.index');
        Route::post('transfers', [BranchTransferController::class, 'store'])->name('transfers.store');
        Route::post('transfers/{transfer}/start-preparing', [BranchTransferController::class, 'startPreparing'])->name('transfers.start-preparing');
        Route::post('transfers/{transfer}/ready-to-ship', [BranchTransferController::class, 'readyToShip'])->name('transfers.ready-to-ship');
        Route::post('transfers/{transfer}/ship', [BranchTransferController::class, 'ship'])->name('transfers.ship');
        Route::post('transfers/{transfer}/receive', [BranchTransferController::class, 'receive'])->name('transfers.receive');
        Route::post('transfers/{transfer}/complete-tini', [BranchTransferController::class, 'completeTini'])->name('transfers.complete-tini');
        Route::post('transfers/{transfer}/cancel', [BranchTransferController::class, 'cancel'])->name('transfers.cancel');
    });

    Route::prefix('compras')->name('purchasing.')->group(function () {
        Route::get('/', PurchasingController::class)->name('index');
        Route::get('recepcion', [PurchasingReceiptController::class, 'index'])->name('receipt.index');
        Route::post('recepcion/{confirmation}/iniciar', [PurchasingReceiptController::class, 'start'])->name('receipt.start');
        Route::post('recepcion/{confirmation}/confirmar', [PurchasingReceiptController::class, 'confirm'])->name('receipt.confirm');
        Route::post('recepcion/facturas/{invoice}/cerrar', [PurchasingReceiptController::class, 'close'])->name('receipt.close');
    });

    Route::prefix('equipo')->name('team.')->group(function () {
        Route::get('empleados', [EmployeeController::class, 'index'])->name('employees.index');
        Route::post('empleados', [EmployeeController::class, 'store'])->name('employees.store');
        Route::put('empleados/{user}/branches', [EmployeeController::class, 'updateBranches'])->name('employees.branches.update');
        Route::put('empleados/{user}/roles', [EmployeeController::class, 'updateRoles'])->name('employees.roles.update');
        Route::patch('empleados/{user}/estado', [EmployeeController::class, 'updateStatus'])->name('employees.status.update');
        Route::delete('empleados/{user}', [EmployeeController::class, 'destroy'])->name('employees.destroy');
    });

    Route::prefix('logistica')->name('logistics.')->group(function () {
        Route::inertia('/', 'logistics/index')->name('index');
        Route::post('fleet/refresh', [FleetController::class, 'refresh'])->name('fleet.refresh');
        Route::post('fleet/alert-test', [FleetController::class, 'testFleetAlerts'])->name('fleet.alert-test');
        Route::post('fleet/alert-settings', [FleetController::class, 'saveAlertSettings'])->name('fleet.alert-settings');
    });
});

require __DIR__.'/settings.php';
