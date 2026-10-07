<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up()
    {
        Schema::create('messages_attachments', function (Blueprint $table) {
            $table->id('id_attachment');
            $table->unsignedBigInteger('id_message');
            $table->string('path');
            $table->string('filename')->nullable();
            $table->string('mime_type')->nullable();
            $table->unsignedBigInteger('size')->nullable();
            $table->timestamp('created_at')->useCurrent();
            $table->foreign('id_message')->references('id_message')->on('messages')->onDelete('cascade');
        });
    }

    public function down()
    {
        Schema::dropIfExists('messages_attachments');
    }
};
