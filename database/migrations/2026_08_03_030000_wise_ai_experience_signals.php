<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('wise_experience_signals', function (Blueprint $table) {
            $table->id();
            $table->foreignId('wise_api_key_id')->index();
            $table->string('signal_type', 40)->index();
            $table->string('intent', 60)->nullable()->index();
            $table->string('action', 40)->nullable()->index();
            $table->string('source', 40)->nullable()->index();
            $table->string('pattern_key', 120)->nullable()->index();
            $table->decimal('weight', 8, 2)->default(0);
            $table->string('idempotency_key', 191)->nullable();
            $table->unsignedBigInteger('wise_turn_id')->nullable()->index();
            $table->json('meta')->nullable();
            $table->timestamps();

            $table->unique(['wise_api_key_id', 'idempotency_key'], 'wise_experience_key_idem');
            $table->index(['wise_api_key_id', 'intent', 'action'], 'wise_experience_lookup');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('wise_experience_signals');
    }
};
