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
        Schema::table('personal_access_tokens', function (Blueprint $table) {
            $table->string('title')->nullable()->after('name');
            $table->text('description')->nullable()->after('title');
            $table->text('access_key')->nullable()->after('description');
            $table->text('api_token')->nullable()->after('access_key');
            $table->text('domain')->nullable()->after('api_token');
            $table->unsignedBigInteger('user_package_id')->nullable()->after('domain');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('personal_access_tokens', function (Blueprint $table) {
            $table->dropColumn(['title', 'description', 'access_key', 'api_token', 'domain']);
        });
    }
};
