<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

/**
 * Online shop fields: whether a product is listed in the organization's shop, and the slug its shop URL uses.
 * Existing products get a slug from their name, unique within their organization.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('products', function (Blueprint $table) {
            $table->string('slug')->nullable()->after('name');
            $table->boolean('show_in_shop')->default(false)->after('is_active');
        });

        $taken = [];

        DB::table('products')->orderBy('id')->select(['id', 'organization_id', 'name'])->each(function (object $product) use (&$taken): void {
            $base = Str::slug($product->name) ?: 'product';
            $slug = $base;

            for ($suffix = 2; isset($taken[$product->organization_id][$slug]); $suffix++) {
                $slug = "{$base}-{$suffix}";
            }

            $taken[$product->organization_id][$slug] = true;
            DB::table('products')->where('id', $product->id)->update(['slug' => $slug]);
        });

        Schema::table('products', function (Blueprint $table) {
            $table->string('slug')->nullable(false)->change();
            $table->unique(['organization_id', 'slug']);
            $table->index(['organization_id', 'show_in_shop', 'is_active']);
        });
    }

    public function down(): void
    {
        Schema::table('products', function (Blueprint $table) {
            $table->dropUnique(['organization_id', 'slug']);
            $table->dropIndex(['organization_id', 'show_in_shop', 'is_active']);
            $table->dropColumn(['slug', 'show_in_shop']);
        });
    }
};
