<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropUnique('users_email_unique');
            $table->string('email')->nullable()->change();
            $table->string('phone')->nullable()->after('email');
            $table->string('whatsapp_phone')->nullable()->after('phone');
            $table->text('facebook_page_link')->nullable()->after('whatsapp_phone');
            $table->boolean('status')->nullable()->after('facebook_page_link');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn(['phone', 'whatsapp_phone', 'facebook_page_link', 'status']);
            $table->string('email')->unique()->change();
        });
    }
};
