<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('orders', function (Blueprint $table) {
            if (!Schema::hasColumn('orders', 'packing_photo')) {
                $table->string('packing_photo')->nullable()->after('alamat_pengiriman');
            }
            if (!Schema::hasColumn('orders', 'tracking_number')) {
                $table->string('tracking_number')->nullable()->after('packing_photo');
            }
            if (!Schema::hasColumn('orders', 'shipping_service')) {
                $table->string('shipping_service')->nullable()->after('tracking_number');
            }
            if (!Schema::hasColumn('orders', 'shipping_cost')) {
                $table->integer('shipping_cost')->nullable()->default(0)->after('shipping_service');
            }
        });
    }

    public function down(): void
    {
        Schema::table('orders', function (Blueprint $table) {
            $table->dropColumn(['packing_photo', 'tracking_number', 'shipping_service', 'shipping_cost']);
        });
    }
};
