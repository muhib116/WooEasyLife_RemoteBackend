<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('landing_ai_jobs', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->unsignedBigInteger('personal_access_token_id');
            $table->unsignedBigInteger('user_id')->nullable()->index();
            $table->string('domain')->nullable();
            // queued | running | completed | failed | cancelled
            $table->string('status', 20)->default('queued');
            $table->string('model', 80)->nullable();
            $table->string('openai_response_id', 120)->nullable();
            $table->json('spec')->nullable();
            $table->json('request_meta')->nullable();
            $table->json('result')->nullable();
            $table->json('warnings')->nullable();
            $table->string('error', 500)->nullable();
            $table->unsignedInteger('input_tokens')->nullable();
            $table->unsignedInteger('output_tokens')->nullable();
            $table->timestamp('finished_at')->nullable();
            $table->timestamps();

            $table->index(['personal_access_token_id', 'created_at']);
            $table->index(['personal_access_token_id', 'status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('landing_ai_jobs');
    }
};
