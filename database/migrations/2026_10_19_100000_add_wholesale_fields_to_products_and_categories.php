<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('products', function (Blueprint $table) {
            $table->boolean('wholesale_enabled')->default(false)->after('pix_discount_percent');
            $table->boolean('wholesale_only')->default(false)->after('wholesale_enabled');
            $table->boolean('wholesale_hide_from_retail')->default(false)->after('wholesale_only');
            $table->json('wholesale_tiers')->nullable()->after('wholesale_hide_from_retail');
        });

        Schema::table('categories', function (Blueprint $table) {
            $table->boolean('is_wholesale')->default(false)->after('is_featured_circle');
        });
    }

    public function down(): void
    {
        Schema::table('products', function (Blueprint $table) {
            $table->dropColumn([
                'wholesale_enabled',
                'wholesale_only',
                'wholesale_hide_from_retail',
                'wholesale_tiers',
            ]);
        });

        Schema::table('categories', function (Blueprint $table) {
            $table->dropColumn('is_wholesale');
        });
    }
};
