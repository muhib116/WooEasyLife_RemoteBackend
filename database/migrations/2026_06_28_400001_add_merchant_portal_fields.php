<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->unsignedBigInteger('merchant_user_id')->nullable()->after('admin_role_id');
            $table->index('merchant_user_id');
        });

        Schema::table('merchant_employees', function (Blueprint $table) {
            $table->unsignedBigInteger('user_id')->nullable()->unique()->after('merchant_user_id');
        });
    }

    public function down(): void
    {
        Schema::table('merchant_employees', function (Blueprint $table) {
            $table->dropUnique(['user_id']);
            $table->dropColumn('user_id');
        });

        Schema::table('users', function (Blueprint $table) {
            $table->dropIndex(['merchant_user_id']);
            $table->dropColumn('merchant_user_id');
        });
    }
};
