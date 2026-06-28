<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('package_hubs', function (Blueprint $table) {
            $table->decimal('package_price', 12, 2)->nullable()->after('order_rate_token');
        });
    }

    public function down(): void
    {
        Schema::table('package_hubs', function (Blueprint $table) {
            $table->dropColumn('package_price');
        });
    }
};
