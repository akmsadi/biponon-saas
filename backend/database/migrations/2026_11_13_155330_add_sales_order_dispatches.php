<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Dispatching against sales orders: a dispatch movement remembers the order line it fills. The price stays on
 * the line, which can no longer change once the order is confirmed.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('stock_movements', function (Blueprint $table) {
            $table->foreignId('sales_order_line_id')->nullable()->after('purchase_order_line_id')->constrained()->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('stock_movements', function (Blueprint $table) {
            $table->dropConstrainedForeignId('sales_order_line_id');
        });
    }
};
