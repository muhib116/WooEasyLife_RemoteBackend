<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('wise_api_keys', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            // Only a SHA-256 hash is stored; the plain key is shown once at creation.
            $table->string('key_hash', 64)->unique();
            $table->string('key_prefix', 16);
            $table->string('status', 20)->default('active')->index();
            $table->unsignedBigInteger('turns_count')->default(0);
            $table->timestamp('last_used_at')->nullable();
            $table->json('meta')->nullable();
            $table->timestamps();
        });

        Schema::create('wise_turns', function (Blueprint $table) {
            $table->id();
            $table->foreignId('wise_api_key_id')->index();
            $table->string('channel', 40)->default('api')->index();
            $table->string('conversation_id')->nullable()->index();
            $table->text('text')->nullable();
            $table->json('payload')->nullable();
            $table->json('decision')->nullable();
            $table->string('status', 20)->default('ok')->index();
            $table->unsignedInteger('latency_ms')->nullable();
            $table->timestamps();

            $table->index('created_at');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('wise_turns');
        Schema::dropIfExists('wise_api_keys');
    }
};
