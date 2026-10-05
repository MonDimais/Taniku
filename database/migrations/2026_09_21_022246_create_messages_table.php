<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('messages', function (Blueprint $table) {
            $table->integer('id_message')->autoIncrement();
            $table->integer('sender_id');
            $table->integer('receiver_id');
            $table->integer('id_order')->nullable();
            $table->text('pesan');
            $table->timestamp('sent_at')->useCurrent();
            $table->timestamp('read_at')->nullable();

            $table->foreign('sender_id')->references('id_user')->on('users');
            $table->foreign('receiver_id')->references('id_user')->on('users');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('messages');
    }
};
