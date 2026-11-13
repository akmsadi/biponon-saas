<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Receiving against purchase orders: a receipt movement remembers the order line it fills and that line's
 * unit cost at the time, and a partly received order can be closed when the rest will not arrive.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('stock_movements', function (Blueprint $table) {
            $table->foreignId('purchase_order_line_id')->nullable()->after('reference_id')->constrained()->nullOnDelete();
            $table->decimal('unit_cost', 18, 2)->nullable()->after('quantity');
        });

        Schema::table('purchase_orders', function (Blueprint $table) {
            $table->timestamp('closed_at')->nullable()->after('cancelled_at');
        });
    }

    public function down(): void
    {
        Schema::table('purchase_orders', function (Blueprint $table) {
            $table->dropColumn('closed_at');
        });

        Schema::table('stock_movements', function (Blueprint $table) {
            $table->dropConstrainedForeignId('purchase_order_line_id');
            $table->dropColumn('unit_cost');
        });
    }
};
