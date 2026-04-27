<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('pherce_intel.reception_confirmations', function (Blueprint $table) {
            $table->id();
            $table->foreignId('branch_id')->constrained('branches')->cascadeOnDelete();
            $table->foreignId('invoice_id')
                ->nullable()
                ->constrained('pherce_intel.purchase_invoices')
                ->nullOnDelete();
            $table->string('tini_invoice_id', 50)->nullable();
            $table->foreignId('confirmed_by')->nullable()->constrained('users')->nullOnDelete();
            $table->string('status', 20)->default('pending'); // pending | confirmed | discrepancy
            $table->text('notes')->nullable();
            $table->timestamp('confirmed_at')->nullable();
            $table->timestamps();
        });

        Schema::create('pherce_intel.reception_confirmation_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('confirmation_id')
                ->constrained('pherce_intel.reception_confirmations')
                ->cascadeOnDelete();
            $table->string('tini_product_id', 50)->nullable();
            $table->string('description', 500)->nullable();
            $table->decimal('expected_qty', 10, 2);
            $table->decimal('received_qty', 10, 2)->default(0);
            $table->boolean('has_discrepancy')->default(false);
            $table->text('discrepancy_notes')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('pherce_intel.reception_confirmation_items');
        Schema::dropIfExists('pherce_intel.reception_confirmations');
    }
};
