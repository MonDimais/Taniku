<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('reviews', function (Blueprint $table) {
            $table->integer('id_review')->autoIncrement();
            $table->integer('id_order')->nullable();
            $table->integer('id_product');
            $table->integer('id_buyer');
            $table->integer('rating');
            $table->text('komentar')->nullable();
            $table->timestamp('created_at')->useCurrent();

            $table->foreign('id_product')->references('id_product')->on('products');
            $table->foreign('id_buyer')->references('id_user')->on('users');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('reviews');
    }
};
