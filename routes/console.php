<?php

use App\Domain\Inventory\Services\BranchStockMatrixImportService;
use App\Domain\Inventory\Services\InventoryProductImportService;
use App\Domain\Inventory\Services\ValuedInventoryImportService;
use App\Domain\Logistics\Services\FleetTelemetryService;
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

Artisan::command('inventory:import-branch-stock-matrix {file} {--source=branch-stock-matrix}', function (BranchStockMatrixImportService $importer): int {
    $file = (string) $this->argument('file');
    $source = (string) $this->option('source');

    if (! is_file($file)) {
        $this->error("No se encontro el archivo: {$file}");

        return self::FAILURE;
    }

    $result = $importer->importCsv($file, $source);

    $this->info('Inventario multi-bodega importado.');
    $this->line("Filas guardadas/actualizadas: {$result['imported']}");
    $this->line("Filas omitidas: {$result['skipped']}");
    $this->line("Filas con bodega no configurada: {$result['unknown_branch']}");

    foreach ($result['by_branch'] as $warehouseCode => $count) {
        $this->line("Bodega {$warehouseCode}: {$count}");
    }

    return self::SUCCESS;
})->purpose('Importar stock de productos por bodegas 10/20/30/40 desde CSV exportado de TINI');

Artisan::command('inventory:import-valued {file} {--branch=1} {--source=valued-csv}', function (ValuedInventoryImportService $importer): int {
    $file = (string) $this->argument('file');
    $branchId = (int) $this->option('branch');
    $source = (string) $this->option('source');

    if (! is_file($file)) {
        $this->error("No se encontro el archivo: {$file}");

        return self::FAILURE;
    }

    $branch = Branch::query()->find($branchId);

    if ($branch === null) {
        $this->error("No existe la sucursal {$branchId}.");

        return self::FAILURE;
    }

    $result = $importer->importCsv($file, $branchId, $source);

    $this->info("Inventario valorado importado para {$branch->name}.");
    $this->line("Productos actualizados: {$result['matched']}");
    $this->line("Productos creados desde el valorado: {$result['created']}");
    $this->line("Codigos del archivo sin producto existente: {$result['missing']}");
    $this->line("Filas omitidas: {$result['skipped']}");

    return self::SUCCESS;
})->purpose('Importar metadatos de inventario valorado por codigo desde CSV exportado');

Artisan::command('fleet:snapshot', function (FleetTelemetryService $fleetTelemetryService): int {
    $dashboard = $fleetTelemetryService->buildDashboard();

    $this->info('Snapshot de flota guardado.');
    $this->line('Vehiculos: '.$dashboard['kpis']['total']);
    $this->line('Alertas criticas: '.$dashboard['kpis']['critical_alerts']);
    $this->line('Riesgos electricos: '.$dashboard['kpis']['electrical_risks']);

    return self::SUCCESS;
})->purpose('Consultar Ubika y guardar telemetria historica de la flota');

Schedule::everyFiveMinutes()
    ->onOneServer()
    ->withoutOverlapping()
    ->group(function (): void {
        Schedule::command('horizon:snapshot');
    });

// Revisar Gmail cada 15 minutos y crear expedientes en la sucursal operativa de recepcion.
Schedule::call(function (): void {
    $configuredBranchId = config('gmail_inbox.branch_id');

    $branch = $configuredBranchId
        ? Branch::query()->where('is_active', true)->find((int) $configuredBranchId)
        : Branch::query()
            ->where('is_active', true)
            ->orderByDesc('is_headquarters')
            ->orderBy('id')
            ->first();

    if ($branch instanceof Branch) {
        FetchInvoiceEmailsJob::dispatch($branch->id);
    }
})
    ->name('fetch-invoice-emails')
    ->everyFifteenMinutes()
    ->onOneServer()
    ->withoutOverlapping()
    ->timezone('America/Guayaquil');

Schedule::command('fleet:snapshot')
    ->name('fleet-snapshot')
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
