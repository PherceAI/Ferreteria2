<?php

use App\Domain\Purchasing\Jobs\FetchInvoiceEmailsJob;
use App\Models\Branch;
use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

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
