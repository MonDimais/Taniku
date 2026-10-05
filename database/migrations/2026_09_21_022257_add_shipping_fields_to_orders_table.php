<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('orders', function (Blueprint $table) {
            // Approve/ship workflow fields. The OrderController's updateStatus()
            // already wrote these columns, but they were missing from the schema,
            // so approving or shipping an order returned HTTP 500.
            $table->string('packing_photo')->nullable()->after('alamat_pengiriman');
            $table->string('tracking_number')->nullable()->after('packing_photo');
            $table->string('shipping_service')->nullable()->after('tracking_number');
            $table->integer('shipping_cost')->nullable()->default(0)->after('shipping_service');
        });
    }

    public function down(): void
    {
        Schema::table('orders', function (Blueprint $table) {
            $table->dropColumn(['packing_photo', 'tracking_number', 'shipping_service', 'shipping_cost']);
        });
    }
};
