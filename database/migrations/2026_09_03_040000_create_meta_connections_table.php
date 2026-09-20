<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('meta_connections', function (Blueprint $table) {
            $table->id();
            // Plain WEL license key (Sanctum bearer) — resolves via AccessToken::findToken.
            $table->string('license_key', 191)->unique();
            $table->text('system_user_token'); // Laravel encrypted cast
            $table->json('granted_scopes')->nullable();
            $table->enum('status', ['active', 'revoked', 'error'])->default('active');
            // Distinguishes unified System User flow from future legacy_oauth reconciliation.
            $table->string('source', 32)->default('system_user');
            $table->timestamp('last_verified_at')->nullable();
            $table->timestamps();

            $table->index('status');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('meta_connections');
    }
};
