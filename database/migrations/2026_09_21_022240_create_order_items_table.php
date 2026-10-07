<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('order_items', function (Blueprint $table) {
            $table->integer('id_order_item')->autoIncrement();
            $table->integer('id_order');
            $table->integer('id_product');
            $table->integer('quantity');
            $table->float('harga_satuan');
            $table->float('subtotal');

            $table->foreign('id_order')->references('id_order')->on('orders');
            $table->foreign('id_product')->references('id_product')->on('products');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('order_items');
    }
};
