<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run `php artisan domains:audit-global-uniqueness` and resolve conflicts before migrating.
     */
    public function up(): void
    {
        Schema::table('websites', function (Blueprint $table) {
            $table->dropUnique(['user_id', 'domain']);
            $table->unique('domain');
        });
    }

    public function down(): void
    {
        Schema::table('websites', function (Blueprint $table) {
            $table->dropUnique(['domain']);
            $table->unique(['user_id', 'domain']);
        });
    }
};
