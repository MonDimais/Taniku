<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('orders', function (Blueprint $table) {
            $table->integer('id_order')->autoIncrement();
            $table->integer('id_buyer');
            $table->integer('id_seller');
            $table->timestamp('tanggal_order')->useCurrent();
            $table->float('total_amount');
            $table->string('status_order', 30)->default('pending');
            $table->text('alamat_pengiriman')->nullable();

            $table->foreign('id_buyer')->references('id_user')->on('users');
            $table->foreign('id_seller')->references('id_user')->on('users');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('orders');
    }
};
