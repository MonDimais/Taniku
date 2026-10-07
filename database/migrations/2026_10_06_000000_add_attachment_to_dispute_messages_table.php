<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up()
    {
        Schema::table('dispute_messages', function (Blueprint $table) {
            if (!Schema::hasColumn('dispute_messages', 'attachment')) {
                $table->text('attachment')->nullable()->after('pesan');
            }
        });
    }

    public function down()
    {
        Schema::table('dispute_messages', function (Blueprint $table) {
            $table->dropColumn('attachment');
        });
    }
};
