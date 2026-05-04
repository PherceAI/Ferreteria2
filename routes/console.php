<?php

use App\Domain\Inventory\Services\InventoryProductImportService;
use App\Domain\Purchasing\Jobs\FetchInvoiceEmailsJob;
use App\Models\Branch;
use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

Artisan::command('inventory:import-products {file} {--branch=1} {--source=csv}', function (InventoryProductImportService $importer): int {
    $file = (string) $this->argument('file');
    $branchId = (int) $this->option('branch');
    $source = (string) $this->option('source');

    if (! is_file($file)) {
        $this->error("No se encontró el archivo: {$file}");

        return self::FAILURE;
    }

    $branch = Branch::query()->find($branchId);

    if ($branch === null) {
        $this->error("No existe la sucursal {$branchId}.");

        return self::FAILURE;
    }

    $result = $importer->importCsv(
        path: $file,
        branchId: $branchId,
        source: $source,
        inventoryUpdatedAt: Carbon::now('America/Guayaquil'),
    );

    $this->info("Inventario importado para {$branch->name}.");
    $this->line("Filas guardadas/actualizadas: {$result['imported']}");
    $this->line("Filas omitidas: {$result['skipped']}");

    return self::SUCCESS;
})->purpose('Importar productos de inventario por sucursal desde un CSV limpio');

Schedule::everyFiveMinutes()
    ->onOneServer()
    ->withoutOverlapping()
    ->group(function (): void {
        Schedule::command('horizon:snapshot');
    });

// Revisar Gmail cada 15 minutos por sucursal activa buscando facturas con adjunto XML
Schedule::call(function (): void {
    Branch::where('is_active', true)->each(function (Branch $branch): void {
        FetchInvoiceEmailsJob::dispatch($branch->id);
    });
})
    ->name('fetch-invoice-emails')
    ->everyFifteenMinutes()
    ->onOneServer()
    ->withoutOverlapping()
    ->timezone('America/Guayaquil');

Schedule::daily()
    ->onOneServer()
    ->timezone('America/Guayaquil')
    ->group(function (): void {
        Schedule::command('backup:clean')->at('01:00')->withoutOverlapping();
        Schedule::command('backup:run')->at('01:30')->withoutOverlapping();
        Schedule::command('backup:monitor')->at('02:00')->withoutOverlapping();
    });
