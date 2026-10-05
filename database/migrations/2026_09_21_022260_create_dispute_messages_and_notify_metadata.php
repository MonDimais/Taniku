<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // The dispute-resolution chat (DisputeController getMessages/send) writes
        // here, but the table was never created — every dispute-message call 500'd.
        if (!Schema::hasTable('dispute_messages')) {
            Schema::create('dispute_messages', function (Blueprint $table) {
                $table->id('id_message');
                $table->unsignedBigInteger('id_dispute');
                $table->unsignedBigInteger('sender_id');
                $table->text('pesan');
                $table->timestamp('sent_at')->nullable();
                $table->timestamps();
                $table->foreign('id_dispute')->references('id_dispute')->on('disputes');
                $table->foreign('sender_id')->references('id_user')->on('users');
            });
        }

        // sendNegotiation/dispute notifications include optional `metadata` JSON.
        if (!Schema::hasColumn('notifications', 'metadata')) {
            Schema::table('notifications', function (Blueprint $table) {
                $table->text('metadata')->nullable()->after('pesan');
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasTable('dispute_messages')) {
            Schema::dropIfExists('dispute_messages');
        }
        if (Schema::hasColumn('notifications', 'metadata')) {
            Schema::table('notifications', function (Blueprint $table) {
                $table->dropColumn('metadata');
            });
        }
    }
};
