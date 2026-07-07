<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('platform_customer_stats', function (Blueprint $table) {
            $table->unsignedTinyInteger('risk_score')->nullable()->after('risk_tier');
            $table->json('ai_profile')->nullable()->after('risk_score');

            $table->index('risk_score');
        });
    }

    public function down(): void
    {
        Schema::table('platform_customer_stats', function (Blueprint $table) {
            $table->dropIndex(['risk_score']);
            $table->dropColumn(['risk_score', 'ai_profile']);
        });
    }
};
