<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // sendNegotiation/counterNego attach the negotiated product to the chat
        // message, but these columns never existed — POST /messages/negotiation
        // returned HTTP 500 ("Unknown column"). Adding them fixes the
        // "network error" the buyer saw when sending a negotiation.
        Schema::table('messages', function (Blueprint $table) {
            if (!Schema::hasColumn('messages', 'id_product')) {
                $table->unsignedBigInteger('id_product')->nullable()->after('id_order');
                $table->string('nama_produk')->nullable()->after('id_product');
                $table->decimal('harga', 15, 2)->nullable()->after('nama_produk');
                $table->string('foto_produk')->nullable()->after('harga');
            }
        });
    }

    public function down(): void
    {
        Schema::table('messages', function (Blueprint $table) {
            $table->dropColumn(['id_product', 'nama_produk', 'harga', 'foto_produk']);
        });
    }
};
