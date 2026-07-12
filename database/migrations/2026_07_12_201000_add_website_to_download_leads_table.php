<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('download_leads', function (Blueprint $table) {
            $table->string('website', 255)->nullable()->after('phone');
            $table->index('website');
        });
    }

    public function down(): void
    {
        Schema::table('download_leads', function (Blueprint $table) {
            $table->dropIndex(['website']);
            $table->dropColumn('website');
        });
    }
};
