<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('disputes', function (Blueprint $table) {
            $table->integer('id_dispute')->autoIncrement();
            $table->integer('id_order');
            $table->integer('opened_by');
            $table->integer('resolved_by')->nullable();
            $table->text('alasan')->nullable();
            $table->text('deskripsi')->nullable();
            $table->string('status', 30)->default('open');
            $table->timestamp('resolved_at')->nullable();

            $table->foreign('id_order')->references('id_order')->on('orders');
            $table->foreign('opened_by')->references('id_user')->on('users');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('disputes');
    }
};
