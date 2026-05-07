<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('fleet_alert_settings', function (Blueprint $table): void {
            $table->id();
            $table->unsignedBigInteger('branch_id')->nullable();
            $table->string('scope', 20);
            $table->string('vehicle_external_id', 80)->default('');
            $table->string('vehicle_name')->nullable();
            $table->unsignedSmallInteger('stopped_minutes')->default(60);
            $table->unsignedSmallInteger('idle_minutes')->default(10);
            $table->unsignedSmallInteger('speed_limit_kph')->default(80);
            $table->decimal('min_voltage_12', 5, 2)->default(11.80);
            $table->decimal('min_voltage_24', 5, 2)->default(23.50);
            $table->unsignedSmallInteger('stale_minutes')->default(30);
            $table->boolean('gps_signal_enabled')->default(true);
            $table->boolean('voltage_enabled')->default(true);
            $table->boolean('route_stop_enabled')->default(true);
            $table->timestamps();

            $table->unique(['branch_id', 'scope', 'vehicle_external_id']);
            $table->index(['branch_id', 'scope']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('fleet_alert_settings');
    }
};
