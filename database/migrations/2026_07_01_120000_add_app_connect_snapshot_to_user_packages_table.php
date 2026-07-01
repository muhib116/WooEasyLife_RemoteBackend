<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('user_packages', function (Blueprint $table) {
            $table->boolean('app_connect')->nullable()->after('features');
            $table->unsignedInteger('total_website_connect')->nullable()->after('app_connect');
        });
    }

    public function down(): void
    {
        Schema::table('user_packages', function (Blueprint $table) {
            $table->dropColumn([
                'app_connect',
                'total_website_connect',
            ]);
        });
    }
};
