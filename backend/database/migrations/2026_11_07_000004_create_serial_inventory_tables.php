<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
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

        Schema::create('stock_movements', function (Blueprint $table) {
            $table->id();
            $table->foreignId('organization_id')->constrained()->cascadeOnDelete();
            $table->foreignId('serial_number_id')->constrained()->restrictOnDelete();
            $table->foreignId('from_warehouse_id')->nullable()->constrained('warehouses')->restrictOnDelete();
            $table->foreignId('to_warehouse_id')->nullable()->constrained('warehouses')->restrictOnDelete();
            $table->string('movement_type', 30);
            $table->string('reference_type', 100)->nullable();
            $table->unsignedBigInteger('reference_id')->nullable();
            $table->foreignId('performed_by')->nullable()->constrained('users')->nullOnDelete();
            $table->text('notes')->nullable();
            $table->timestamp('occurred_at');
            $table->timestamps();

            $table->index(['organization_id', 'occurred_at'], 'stock_moves_org_time_idx');
            $table->index(['organization_id', 'serial_number_id', 'occurred_at'], 'stock_moves_serial_time_idx');
            $table->index(['organization_id', 'reference_type', 'reference_id'], 'stock_moves_reference_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('stock_movements');
        Schema::dropIfExists('serial_numbers');
    }
};
