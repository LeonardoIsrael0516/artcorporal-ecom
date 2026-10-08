<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('orders', function (Blueprint $table) {
            $table->decimal('shipping_amount', 10, 2)->nullable()->after('amount');
            $table->string('shipping_service')->nullable()->after('shipping_amount');
            $table->string('shipping_provider', 32)->nullable()->after('shipping_service');
            $table->unsignedInteger('shipping_days')->nullable()->after('shipping_provider');
            $table->json('shipping_address')->nullable()->after('shipping_days');
            $table->string('shipping_tracking')->nullable()->after('shipping_address');
            $table->string('shipping_label_id')->nullable()->after('shipping_tracking');
        });

        if (Schema::hasTable('commerce_carts')) {
            Schema::table('commerce_carts', function (Blueprint $table) {
                $table->json('shipping_quote')->nullable();
                $table->json('shipping_address')->nullable();
            });
        }
    }

    public function down(): void
    {
        Schema::table('orders', function (Blueprint $table) {
            $table->dropColumn([
                'shipping_amount',
                'shipping_service',
                'shipping_provider',
                'shipping_days',
                'shipping_address',
                'shipping_tracking',
                'shipping_label_id',
            ]);
        });

        if (Schema::hasTable('commerce_carts')) {
            Schema::table('commerce_carts', function (Blueprint $table) {
                $table->dropColumn(['shipping_quote', 'shipping_address']);
            });
        }
    }
};
