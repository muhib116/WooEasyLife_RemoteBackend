<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('messenger_page_connections', function (Blueprint $table) {
            if (! Schema::hasColumn('messenger_page_connections', 'instagram_business_account_id')) {
                $table->string('instagram_business_account_id', 64)->nullable()->after('page_id');
                $table->index('instagram_business_account_id', 'messenger_page_ig_account_idx');
            }
            if (! Schema::hasColumn('messenger_page_connections', 'instagram_username')) {
                $table->string('instagram_username', 191)->nullable()->after('instagram_business_account_id');
            }
        });
    }

    public function down(): void
    {
        Schema::table('messenger_page_connections', function (Blueprint $table) {
            if (Schema::hasColumn('messenger_page_connections', 'instagram_username')) {
                $table->dropColumn('instagram_username');
            }
            if (Schema::hasColumn('messenger_page_connections', 'instagram_business_account_id')) {
                $table->dropIndex('messenger_page_ig_account_idx');
                $table->dropColumn('instagram_business_account_id');
            }
        });
    }
};
