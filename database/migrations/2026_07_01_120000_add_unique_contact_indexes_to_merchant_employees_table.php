<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('merchant_employees', function (Blueprint $table) {
            $table->unique(['merchant_user_id', 'email'], 'merchant_employees_merchant_email_unique');
            $table->unique(['merchant_user_id', 'phone'], 'merchant_employees_merchant_phone_unique');
        });
    }

    public function down(): void
    {
        Schema::table('merchant_employees', function (Blueprint $table) {
            $table->dropUnique('merchant_employees_merchant_email_unique');
            $table->dropUnique('merchant_employees_merchant_phone_unique');
        });
    }
};
