<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Returns against sales orders: each line counts the units that came back. Return movements link to the line
 * through stock_movements.sales_order_line_id, like dispatches.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('sales_order_lines', function (Blueprint $table) {
            $table->unsignedInteger('quantity_returned')->default(0)->after('quantity_dispatched');
        });
    }

    public function down(): void
    {
        Schema::table('sales_order_lines', function (Blueprint $table) {
            $table->dropColumn('quantity_returned');
        });
    }
};
