<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('payments_escrow', function (Blueprint $table) {
            $table->integer('id_payment')->autoIncrement();
            $table->integer('id_order');
            $table->string('metode_pembayaran', 50);
            $table->float('jumlah');
            $table->string('status_pembayaran', 30)->default('pending');
            $table->string('status_escrow', 30)->default('held');
            $table->timestamp('paid_at')->nullable();
            $table->timestamp('released_at')->nullable();

            $table->foreign('id_order')->references('id_order')->on('orders');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('payments_escrow');
    }
};
