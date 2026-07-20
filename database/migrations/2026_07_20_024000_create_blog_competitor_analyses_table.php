<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('blog_competitor_analyses', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $table->string('keyword', 255);
            $table->string('cluster', 64)->nullable();
            $table->json('competitor_urls');
            $table->json('snapshots_json')->nullable();
            $table->json('insight_json')->nullable();
            $table->text('summary_bn')->nullable();
            $table->unsignedTinyInteger('beat_score')->nullable();
            $table->unsignedInteger('prompt_tokens')->default(0);
            $table->unsignedInteger('completion_tokens')->default(0);
            $table->timestamps();

            $table->index(['keyword']);
            $table->index(['cluster', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('blog_competitor_analyses');
    }
};
