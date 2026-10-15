<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        $missingEnabled = ! Schema::hasColumn('users', 'courier_history_enabled');
        $missingLimit = ! Schema::hasColumn('users', 'courier_history_daily_limit');

        if ($missingEnabled || $missingLimit) {
            Schema::table('users', function (Blueprint $table) use ($missingEnabled, $missingLimit) {
                if ($missingEnabled) {
                    $table->boolean('courier_history_enabled')->default(false);
                }
                if ($missingLimit) {
                    $table->unsignedInteger('courier_history_daily_limit')->default(0);
                }
            });
        }

        if (Schema::hasTable('courier_history_uses')) {
            return;
        }

        Schema::create('courier_history_uses', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('phone', 32);
            $table->date('checked_on');
            $table->timestamps();

            $table->unique(['user_id', 'phone', 'checked_on']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('courier_history_uses');

        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn(['courier_history_enabled', 'courier_history_daily_limit']);
        });
    }
};
