<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('blog_ai_memories', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $table->string('type', 32);
            $table->string('content', 500);
            $table->string('normalized_key', 190)->nullable();
            $table->string('cluster', 64)->nullable();
            $table->string('source', 32)->default('manual');
            $table->unsignedTinyInteger('priority')->default(50);
            $table->unsignedInteger('hits')->default(0);
            $table->boolean('is_active')->default(true);
            $table->json('meta_json')->nullable();
            $table->timestamp('last_used_at')->nullable();
            $table->timestamps();

            $table->unique(['type', 'normalized_key']);
            $table->index(['is_active', 'type', 'priority']);
            $table->index(['cluster', 'is_active']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('blog_ai_memories');
    }
};
