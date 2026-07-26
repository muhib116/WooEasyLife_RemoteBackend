<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('messenger_forward_retries', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('messenger_page_connection_id');
            $table->string('page_id', 64)->nullable();
            $table->string('fingerprint', 64);
            $table->json('payload');
            $table->unsignedTinyInteger('attempts')->default(0);
            $table->unsignedTinyInteger('max_attempts')->default(3);
            $table->timestamp('next_retry_at')->nullable();
            $table->timestamp('last_attempt_at')->nullable();
            $table->string('last_error', 255)->nullable();
            $table->string('status', 32)->default('pending');
            $table->timestamps();

            $table->index(['status', 'next_retry_at'], 'messenger_forward_retries_due_idx');
            $table->index('messenger_page_connection_id', 'messenger_forward_retries_connection_idx');
            $table->index(['messenger_page_connection_id', 'fingerprint', 'status'], 'messenger_forward_retries_fp_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('messenger_forward_retries');
    }
};
