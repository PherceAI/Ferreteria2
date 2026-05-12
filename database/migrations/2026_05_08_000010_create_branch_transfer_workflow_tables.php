<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('pherce_intel.branch_transfers', function (Blueprint $table) {
            $table->id();
            $table->foreignId('source_branch_id')->constrained('branches')->cascadeOnDelete();
            $table->foreignId('destination_branch_id')->constrained('branches')->cascadeOnDelete();
            $table->foreignId('requested_by')->constrained('users')->cascadeOnDelete();
            $table->foreignId('prepared_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('shipped_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('received_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('tini_completed_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('cancelled_by')->nullable()->constrained('users')->nullOnDelete();
            $table->string('status', 30)->default('requested');
            $table->text('request_notes')->nullable();
            $table->text('preparation_notes')->nullable();
            $table->text('shipping_notes')->nullable();
            $table->text('reception_notes')->nullable();
            $table->text('cancellation_reason')->nullable();
            $table->timestamp('preparing_at')->nullable();
            $table->timestamp('ready_to_ship_at')->nullable();
            $table->timestamp('shipped_at')->nullable();
            $table->timestamp('received_at')->nullable();
            $table->timestamp('tini_completed_at')->nullable();
            $table->timestamp('cancelled_at')->nullable();
            $table->timestamps();

            $table->index(['source_branch_id', 'status']);
            $table->index(['destination_branch_id', 'status']);
            $table->index(['requested_by', 'created_at']);
        });

        Schema::create('pherce_intel.branch_transfer_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('branch_transfer_id')
                ->constrained('pherce_intel.branch_transfers')
                ->cascadeOnDelete();
            $table->foreignId('inventory_product_id')
                ->nullable()
                ->constrained('pherce_intel.inventory_products')
                ->nullOnDelete();
            $table->string('product_code', 80);
            $table->string('product_name', 500);
            $table->string('unit', 30)->nullable();
            $table->decimal('source_stock_snapshot', 14, 3)->nullable();
            $table->boolean('source_stock_verified')->default(false);
            $table->decimal('requested_qty', 14, 3);
            $table->decimal('prepared_qty', 14, 3)->nullable();
            $table->decimal('received_qty', 14, 3)->nullable();
            $table->boolean('has_discrepancy')->default(false);
            $table->text('preparation_notes')->nullable();
            $table->text('reception_notes')->nullable();
            $table->timestamps();

            $table->index(['branch_transfer_id', 'product_code']);
        });

        Schema::create('pherce_intel.branch_transfer_events', function (Blueprint $table) {
            $table->id();
            $table->foreignId('branch_transfer_id')
                ->constrained('pherce_intel.branch_transfers')
                ->cascadeOnDelete();
            $table->foreignId('user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('type', 50);
            $table->string('title', 180);
            $table->text('body')->nullable();
            $table->json('metadata')->nullable();
            $table->timestamps();

            $table->index(['branch_transfer_id', 'created_at']);
            $table->index(['type', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('pherce_intel.branch_transfer_events');
        Schema::dropIfExists('pherce_intel.branch_transfer_items');
        Schema::dropIfExists('pherce_intel.branch_transfers');
    }
};
