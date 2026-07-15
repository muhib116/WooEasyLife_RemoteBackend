<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('blog_ai_runs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('blog_ai_session_id')->constrained('blog_ai_sessions')->cascadeOnDelete();
            $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();
            $table->unsignedBigInteger('blog_post_id')->nullable()->index();
            $table->string('mode', 16)->default('auto');
            $table->string('status', 32)->default('pending');
            $table->string('current_step', 64)->nullable();
            $table->unsignedTinyInteger('progress_pct')->default(0);
            $table->unsignedTinyInteger('live_score')->default(0);
            $table->json('score_breakdown')->nullable();
            $table->json('step_log')->nullable();
            $table->json('revision_counts')->nullable();
            $table->json('input_json')->nullable();
            $table->text('last_error')->nullable();
            $table->timestamp('started_at')->nullable();
            $table->timestamp('finished_at')->nullable();
            $table->timestamps();

            $table->index(['user_id', 'created_at']);
            $table->index(['status']);
        });

        Schema::table('blog_posts', function (Blueprint $table) {
            $table->unsignedTinyInteger('ai_quality_score')->nullable()->after('cluster');
            $table->json('ai_quality_breakdown')->nullable()->after('ai_quality_score');
            $table->unsignedBigInteger('ai_run_id')->nullable()->index()->after('ai_quality_breakdown');
        });

        Schema::table('blog_ai_runs', function (Blueprint $table) {
            $table->foreign('blog_post_id')->references('id')->on('blog_posts')->nullOnDelete();
        });

        Schema::table('blog_posts', function (Blueprint $table) {
            $table->foreign('ai_run_id')->references('id')->on('blog_ai_runs')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('blog_posts', function (Blueprint $table) {
            $table->dropForeign(['ai_run_id']);
            $table->dropColumn(['ai_quality_score', 'ai_quality_breakdown', 'ai_run_id']);
        });

        Schema::table('blog_ai_runs', function (Blueprint $table) {
            $table->dropForeign(['blog_post_id']);
        });

        Schema::dropIfExists('blog_ai_runs');
    }
};
