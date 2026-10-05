<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('orders', function (Blueprint $table) {
            // Set by the DisputeController when a dispute is resolved so the
            // order history shows how it was settled. Missing this column made
            // POST /api/disputes/{id}/resolve return HTTP 500.
            $table->string('dispute_resolution')->nullable()->after('shipping_cost');
        });
    }

    public function down(): void
    {
        Schema::table('orders', function (Blueprint $table) {
            $table->dropColumn('dispute_resolution');
        });
    }
};
