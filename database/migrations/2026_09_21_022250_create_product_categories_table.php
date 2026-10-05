<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('product_categories', function (Blueprint $table) {
            $table->integer('id_product');
            $table->integer('id_kategori');

            $table->foreign('id_product')->references('id_product')->on('products');
            $table->foreign('id_kategori')->references('id_kategori')->on('categories');
            $table->primary(['id_product', 'id_kategori']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('product_categories');
    }
};
