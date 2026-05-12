<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('pherce_intel.inventory_alert_settings', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('branch_id')->constrained('branches')->cascadeOnDelete();
            $table->string('scope_type', 20);
            $table->string('scope_key', 180)->default('');
            $table->string('scope_label', 220);
            $table->json('settings');
            $table->foreignId('updated_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->unique(['branch_id', 'scope_type', 'scope_key'], 'inventory_alert_settings_scope_unique');
            $table->index(['branch_id', 'scope_type'], 'inventory_alert_settings_branch_scope_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('pherce_intel.inventory_alert_settings');
    }
};
