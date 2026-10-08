<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('products', function (Blueprint $table) {
            $table->string('sku', 64)->nullable()->after('slug')->index();
            $table->unsignedInteger('stock')->default(0)->after('price');
            $table->boolean('track_stock')->default(true)->after('stock');
            $table->unsignedInteger('weight_g')->nullable()->after('track_stock');
            $table->decimal('height_cm', 8, 2)->nullable()->after('weight_g');
            $table->decimal('width_cm', 8, 2)->nullable()->after('height_cm');
            $table->decimal('length_cm', 8, 2)->nullable()->after('width_cm');
            $table->decimal('compare_at_price', 10, 2)->nullable()->after('price');
            $table->decimal('pix_discount_percent', 5, 2)->nullable()->after('compare_at_price');
            $table->json('gallery')->nullable()->after('image');
            $table->text('technical_description')->nullable()->after('description');
            $table->text('attention_notes')->nullable()->after('technical_description');
        });
    }

    public function down(): void
    {
        Schema::table('products', function (Blueprint $table) {
            $table->dropColumn([
                'sku',
                'stock',
                'track_stock',
                'weight_g',
                'height_cm',
                'width_cm',
                'length_cm',
                'compare_at_price',
                'pix_discount_percent',
                'gallery',
                'technical_description',
                'attention_notes',
            ]);
        });
    }
};
