<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('subscription_alert_logs', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('user_id');
            $table->string('domain', 255)->nullable();
            $table->string('alert_type', 50);
            $table->string('alert_key', 191)->unique();
            $table->string('channel', 20)->default('system');
            $table->json('payload')->nullable();
            $table->timestamps();

            $table->index(['user_id', 'alert_type']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('subscription_alert_logs');
    }
};
