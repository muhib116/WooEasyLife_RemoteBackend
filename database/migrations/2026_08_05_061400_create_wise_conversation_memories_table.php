<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('wise_conversation_memories')) {
            return;
        }

        Schema::create('wise_conversation_memories', function (Blueprint $table) {
            $table->id();
            $table->foreignId('wise_api_key_id')->constrained('wise_api_keys')->cascadeOnDelete();
            $table->string('conversation_id', 191);
            $table->text('summary')->nullable();
            $table->string('goal', 40)->nullable();
            $table->json('preferences')->nullable();
            $table->unsignedBigInteger('last_turn_id')->nullable();
            $table->timestamps();

            $table->unique(['wise_api_key_id', 'conversation_id'], 'wise_conv_mem_key_conv_unique');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('wise_conversation_memories');
    }
};
