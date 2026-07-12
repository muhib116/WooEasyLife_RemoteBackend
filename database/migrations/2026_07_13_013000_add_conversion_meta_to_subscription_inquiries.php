<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('subscription_inquiries', function (Blueprint $table) {
            if (! Schema::hasColumn('subscription_inquiries', 'conversion_meta')) {
                $table->json('conversion_meta')->nullable()->after('converted_access_token_id');
            }
        });
    }

    public function down(): void
    {
        Schema::table('subscription_inquiries', function (Blueprint $table) {
            if (Schema::hasColumn('subscription_inquiries', 'conversion_meta')) {
                $table->dropColumn('conversion_meta');
            }
        });
    }
};
