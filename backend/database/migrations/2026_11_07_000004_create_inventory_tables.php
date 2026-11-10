<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Quantity-based inventory: stock_balances holds the current on-hand quantity per variant and warehouse,
 * stock_movements is the append-only ledger of every change. Serial numbers are optional per-unit tracking
 * layered on top, linked to the movements they took part in.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('stock_balances', function (Blueprint $table) {
            $table->id();
            $table->foreignId('organization_id')->constrained()->cascadeOnDelete();
            $table->foreignId('product_variant_id')->constrained()->restrictOnDelete();
            $table->foreignId('warehouse_id')->constrained()->restrictOnDelete();
            $table->unsignedInteger('quantity')->default(0);
            $table->timestamps();

            $table->unique(['organization_id', 'product_variant_id', 'warehouse_id'], 'stock_balances_org_variant_wh_unique');
            $table->index(['organization_id', 'warehouse_id']);
        });

        Schema::create('stock_movements', function (Blueprint $table) {
            $table->id();
            $table->foreignId('organization_id')->constrained()->cascadeOnDelete();
            $table->foreignId('product_variant_id')->constrained()->restrictOnDelete();
            $table->foreignId('from_warehouse_id')->nullable()->constrained('warehouses')->restrictOnDelete();
            $table->foreignId('to_warehouse_id')->nullable()->constrained('warehouses')->restrictOnDelete();
            $table->unsignedInteger('quantity');
            $table->string('movement_type', 30);
            $table->string('reference_type', 100)->nullable();
            $table->unsignedBigInteger('reference_id')->nullable();
            $table->foreignId('performed_by')->nullable()->constrained('users')->nullOnDelete();
            $table->text('notes')->nullable();
            $table->timestamp('occurred_at');
            $table->timestamps();

            $table->index(['organization_id', 'occurred_at'], 'stock_moves_org_time_idx');
            $table->index(['organization_id', 'product_variant_id', 'occurred_at'], 'stock_moves_variant_time_idx');
            $table->index(['organization_id', 'reference_type', 'reference_id'], 'stock_moves_reference_idx');
        });

        Schema::create('serial_numbers', function (Blueprint $table) {
            $table->id();
            $table->foreignId('organization_id')->constrained()->cascadeOnDelete();
            $table->foreignId('product_variant_id')->constrained()->restrictOnDelete();
            $table->foreignId('current_warehouse_id')->nullable()->constrained('warehouses')->restrictOnDelete();
            $table->string('serial_number', 150);
            $table->string('status', 30)->default('in_stock');
            $table->timestamp('received_at')->nullable();
            $table->timestamps();

            $table->unique(['organization_id', 'serial_number']);
            $table->index(['organization_id', 'product_variant_id', 'status']);
            $table->index(['organization_id', 'current_warehouse_id', 'status']);
        });

        Schema::create('serial_number_stock_movement', function (Blueprint $table) {
            $table->foreignId('stock_movement_id')->constrained()->cascadeOnDelete();
            $table->foreignId('serial_number_id')->constrained()->restrictOnDelete();

            $table->primary(['stock_movement_id', 'serial_number_id']);
            $table->index('serial_number_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('serial_number_stock_movement');
        Schema::dropIfExists('serial_numbers');
        Schema::dropIfExists('stock_movements');
        Schema::dropIfExists('stock_balances');
    }
};
