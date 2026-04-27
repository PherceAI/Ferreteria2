<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('pherce_intel.suppliers', function (Blueprint $table) {
            $table->id();
            $table->foreignId('branch_id')->constrained('branches')->cascadeOnDelete();
            $table->text('ruc');
            $table->string('name', 255);
            $table->string('email', 255)->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestamps();
            $table->unique(['branch_id', 'ruc']);
        });

        Schema::create('pherce_intel.purchase_invoices', function (Blueprint $table) {
            $table->id();
            $table->foreignId('branch_id')->constrained('branches')->cascadeOnDelete();
            $table->foreignId('supplier_id')->constrained('pherce_intel.suppliers')->cascadeOnDelete();
            $table->string('invoice_number', 50);
            $table->string('access_key', 49)->unique();
            $table->date('emission_date');
            $table->decimal('total', 12, 2);
            $table->string('status', 20)->default('pending'); // pending | confirmed | discrepancy
            $table->string('gmail_message_id', 255)->unique();
            $table->string('from_email', 255)->nullable();
            $table->timestamps();
        });

        Schema::create('pherce_intel.purchase_invoice_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('invoice_id')
                ->constrained('pherce_intel.purchase_invoices')
                ->cascadeOnDelete();
            $table->string('code', 50)->nullable();
            $table->string('description', 500);
            $table->decimal('quantity', 10, 2);
            $table->decimal('unit_price', 12, 2);
            $table->decimal('subtotal', 12, 2);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('pherce_intel.purchase_invoice_items');
        Schema::dropIfExists('pherce_intel.purchase_invoices');
        Schema::dropIfExists('pherce_intel.suppliers');
    }
};
