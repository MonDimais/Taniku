<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('notifications', function (Blueprint $table) {
            $table->integer('id_notification')->autoIncrement();
            $table->integer('id_user');
            $table->integer('id_order')->nullable();
            $table->string('tipe', 50);
            $table->text('pesan');
            $table->integer('is_read')->default(0);
            $table->timestamp('created_at')->useCurrent();

            $table->foreign('id_user')->references('id_user')->on('users');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('notifications');
    }
};
