<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('verifications', function (Blueprint $table) {
            $table->integer('id_verification')->autoIncrement();
            $table->integer('id_user');
            $table->string('jenis_verifikasi', 50);
            $table->string('dokumen', 255)->nullable();
            $table->string('status', 20)->default('pending');
            $table->integer('verified_by')->nullable();
            $table->timestamp('verified_at')->nullable();

            $table->foreign('id_user')->references('id_user')->on('users');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('verifications');
    }
};
