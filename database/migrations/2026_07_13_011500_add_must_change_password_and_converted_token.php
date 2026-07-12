<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            if (! Schema::hasColumn('users', 'must_change_password')) {
                $table->boolean('must_change_password')->default(false)->after('password');
            }
        });

        Schema::table('subscription_inquiries', function (Blueprint $table) {
            if (! Schema::hasColumn('subscription_inquiries', 'converted_access_token_id')) {
                $table->unsignedBigInteger('converted_access_token_id')->nullable()->after('package_payment_request_id');
            }
        });
    }

    public function down(): void
    {
        Schema::table('subscription_inquiries', function (Blueprint $table) {
            if (Schema::hasColumn('subscription_inquiries', 'converted_access_token_id')) {
                $table->dropColumn('converted_access_token_id');
            }
        });

        Schema::table('users', function (Blueprint $table) {
            if (Schema::hasColumn('users', 'must_change_password')) {
                $table->dropColumn('must_change_password');
            }
        });
    }
};
