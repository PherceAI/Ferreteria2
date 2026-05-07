<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('pherce_intel.reception_confirmation_items', function (Blueprint $table) {
            $table->foreignId('purchase_invoice_item_id')
                ->nullable()
                ->after('confirmation_id')
                ->constrained('pherce_intel.purchase_invoice_items')
                ->nullOnDelete();
            $table->string('condition_status', 20)->default('ok')->after('received_qty');
        });

        Schema::create('pherce_intel.purchase_invoice_events', function (Blueprint $table) {
            $table->id();
            $table->foreignId('branch_id')->constrained('branches')->cascadeOnDelete();
            $table->foreignId('invoice_id')
                ->constrained('pherce_intel.purchase_invoices')
                ->cascadeOnDelete();
            $table->foreignId('reception_confirmation_id')
                ->nullable()
                ->constrained('pherce_intel.reception_confirmations')
                ->nullOnDelete();
            $table->foreignId('user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('type', 50);
            $table->string('title', 180);
            $table->text('body')->nullable();
            $table->json('metadata')->nullable();
            $table->timestamps();

            $table->index(['branch_id', 'type']);
            $table->index(['invoice_id', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('pherce_intel.purchase_invoice_events');

        Schema::table('pherce_intel.reception_confirmation_items', function (Blueprint $table) {
            $table->dropConstrainedForeignId('purchase_invoice_item_id');
            $table->dropColumn('condition_status');
        });
    }
};
