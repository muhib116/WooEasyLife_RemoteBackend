<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('download_leads', function (Blueprint $table) {
            $table->id();
            $table->string('name', 120);
            $table->string('phone', 20)->index();
            $table->timestamp('phone_verified_at')->nullable();
            $table->string('download_token', 64)->nullable()->unique();
            $table->timestamp('download_token_expires_at')->nullable();
            $table->unsignedInteger('downloads_count')->default(0);
            $table->timestamp('last_download_at')->nullable();
            $table->string('last_asset', 32)->nullable();
            $table->string('ip', 45)->nullable();
            $table->string('user_agent', 512)->nullable();
            $table->timestamps();

            $table->index(['phone', 'phone_verified_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('download_leads');
    }
};
