<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('product_documents', function (Blueprint $table) {
            $table->integer('id_document')->autoIncrement();
            $table->integer('id_product');
            $table->string('nama_berkas', 255);
            $table->string('path_berkas', 500);
            $table->string('tipe_berkas', 20);
            $table->bigInteger('ukuran')->default(0);
            $table->timestamp('created_at')->useCurrent();

            $table->foreign('id_product')->references('id_product')->on('products');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('product_documents');
    }
};
