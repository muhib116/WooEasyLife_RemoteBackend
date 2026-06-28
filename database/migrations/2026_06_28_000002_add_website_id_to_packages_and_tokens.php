<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     *
     * Nullable columns only — existing rows keep working via domain strings.
     * No foreign key constraint to avoid long locks on large tables in production;
     * integrity is enforced in application code.
     */
    public function up(): void
    {
        Schema::table('user_packages', function (Blueprint $table) {
            if (! Schema::hasColumn('user_packages', 'website_id')) {
                $table->unsignedBigInteger('website_id')->nullable()->after('user_id');
                $table->index('website_id');
            }
        });

        Schema::table('personal_access_tokens', function (Blueprint $table) {
            if (! Schema::hasColumn('personal_access_tokens', 'website_id')) {
                $table->unsignedBigInteger('website_id')->nullable()->after('tokenable_id');
                $table->index('website_id');
            }
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('user_packages', function (Blueprint $table) {
            if (Schema::hasColumn('user_packages', 'website_id')) {
                $table->dropIndex(['website_id']);
                $table->dropColumn('website_id');
            }
        });

        Schema::table('personal_access_tokens', function (Blueprint $table) {
            if (Schema::hasColumn('personal_access_tokens', 'website_id')) {
                $table->dropIndex(['website_id']);
                $table->dropColumn('website_id');
            }
        });
    }
};
