<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('pherce_intel.fleet_vehicle_snapshots', function (Blueprint $table) {
            $table->id();
            $table->string('external_id', 80);
            $table->string('unique_id', 120)->nullable();
            $table->string('name', 160);
            $table->string('status', 20);
            $table->string('online_raw', 40)->nullable();
            $table->decimal('speed', 8, 2)->default(0);
            $table->decimal('latitude', 11, 8)->nullable();
            $table->decimal('longitude', 11, 8)->nullable();
            $table->unsignedSmallInteger('course')->nullable();
            $table->boolean('engine_on')->default(false);
            $table->boolean('blocked')->default(false);
            $table->unsignedSmallInteger('satellites')->nullable();
            $table->decimal('vehicle_voltage', 6, 2)->nullable();
            $table->unsignedSmallInteger('gps_battery_percent')->nullable();
            $table->unsignedInteger('stop_duration_sec')->default(0);
            $table->decimal('total_distance_km', 12, 2)->nullable();
            $table->decimal('engine_hours_total', 12, 2)->nullable();
            $table->timestamp('reported_at')->nullable();
            $table->json('tail')->nullable();
            $table->json('raw_flags')->nullable();
            $table->timestamps();

            $table->index(['external_id', 'reported_at']);
            $table->index(['status', 'reported_at']);
            $table->index('reported_at');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('pherce_intel.fleet_vehicle_snapshots');
    }
};
