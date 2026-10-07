<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('products', function (Blueprint $table) {
            $table->integer('id_product')->autoIncrement();
            $table->integer('id_seller');
            $table->string('nama_produk', 100);
            $table->text('deskripsi')->nullable();
            $table->float('harga');
            $table->integer('stok');
            $table->string('satuan', 50)->default('pcs');
            $table->string('status', 20)->default('pending');
            $table->string('grade', 20)->nullable();
            $table->string('foto', 255)->nullable();
            $table->timestamp('created_at')->useCurrent();

            $table->foreign('id_seller')->references('id_seller')->on('seller_profiles');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('products');
    }
};
