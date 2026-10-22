<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasColumn('personal_access_tokens', 'store_forward_secret')) {
            return;
        }

        Schema::table('personal_access_tokens', function (Blueprint $table) {
            $table->text('store_forward_secret')->nullable();
        });
    }

    public function down(): void
    {
        if (! Schema::hasColumn('personal_access_tokens', 'store_forward_secret')) {
            return;
        }

        Schema::table('personal_access_tokens', function (Blueprint $table) {
            $table->dropColumn('store_forward_secret');
        });
    }
};
