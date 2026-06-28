<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('user_packages', function (Blueprint $table) {
            $table->string('plan_type', 20)->default('legacy')->after('package_hub_id');
            $table->unsignedInteger('order_rate_token')->nullable()->after('plan_type');
            $table->string('package_duration', 30)->nullable()->after('order_rate_token');
            $table->json('features')->nullable()->after('package_duration');
        });
    }

    public function down(): void
    {
        Schema::table('user_packages', function (Blueprint $table) {
            $table->dropColumn([
                'plan_type',
                'order_rate_token',
                'package_duration',
                'features',
            ]);
        });
    }
};
