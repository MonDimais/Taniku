<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::hasTable('dispute_attachments')) {
            Schema::create('dispute_attachments', function (Blueprint $table) {
                $table->id('id_attachment');
                $table->unsignedBigInteger('id_message');
                $table->string('path', 512);
                $table->string('filename', 255)->nullable();
                $table->string('mime_type', 128)->nullable();
                $table->unsignedBigInteger('size')->nullable();
                $table->timestamp('created_at')->useCurrent();
                $table->foreign('id_message')
                    ->references('id_message')->on('dispute_messages')
                    ->onDelete('cascade');
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasTable('dispute_attachments')) {
            Schema::dropIfExists('dispute_attachments');
        }
    }
};
