<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('package_hubs', function (Blueprint $table) {
            $table->string('package_duration')->nullable()->after('per_order_rate');
            $table->unsignedInteger('trial_days')->nullable()->after('package_duration');
            $table->unsignedInteger('order_rate_token')->nullable()->after('trial_days');
            $table->boolean('app_connect')->default(false)->after('order_rate_token');
            $table->unsignedInteger('total_website_connect')->nullable()->after('app_connect');
            $table->json('features')->nullable()->after('total_website_connect');
        });
    }

    public function down(): void
    {
        Schema::table('package_hubs', function (Blueprint $table) {
            $table->dropColumn([
                'package_duration',
                'trial_days',
                'order_rate_token',
                'app_connect',
                'total_website_connect',
                'features',
            ]);
        });
    }
};
