<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('blog_ai_sessions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();
            $table->string('status', 32)->default('started');
            $table->string('locale', 8)->default('bn');
            $table->string('cluster', 64)->nullable();
            $table->string('seed_topic')->nullable();
            $table->json('keywords_json')->nullable();
            $table->json('hooks_json')->nullable();
            $table->json('selected_hook_ids')->nullable();
            $table->json('outline_json')->nullable();
            $table->json('link_plan_json')->nullable();
            $table->json('draft_json')->nullable();
            $table->unsignedInteger('prompt_tokens')->default(0);
            $table->unsignedInteger('completion_tokens')->default(0);
            $table->unsignedInteger('total_tokens')->default(0);
            $table->text('last_error')->nullable();
            $table->timestamps();

            $table->index(['user_id', 'created_at']);
            $table->index(['status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('blog_ai_sessions');
    }
};
