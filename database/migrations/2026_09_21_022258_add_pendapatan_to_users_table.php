<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            // Cumulative earnings. Sellers accrue this when the admin releases
            // their escrow; admins accrue the platform fee portion.
            $table->decimal('pendapatan', 15, 2)->default(0)->after('alamat');
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn('pendapatan');
        });
    }
};
