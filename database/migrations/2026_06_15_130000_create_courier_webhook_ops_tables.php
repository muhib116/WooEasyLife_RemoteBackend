<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('courier_webhook_events', function (Blueprint $table) {
            $table->id();
            $table->string('partner', 32);
            $table->string('environment', 16)->default('live');
            $table->string('consignment_id', 128)->nullable();
            $table->unsignedBigInteger('shipment_id')->nullable();
            $table->unsignedBigInteger('access_token_id')->nullable();
            $table->string('site_url', 512)->nullable();
            $table->unsignedInteger('wc_order_id')->nullable();
            $table->string('event_type', 64)->nullable();
            $table->string('forward_status', 32)->default('pending');
            $table->string('forward_message', 255)->nullable();
            $table->json('payload_summary')->nullable();
            $table->timestamps();

            $table->index(['partner', 'created_at']);
            $table->index(['access_token_id', 'created_at']);
            $table->index('forward_status');
        });

        Schema::create('courier_forward_retries', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('shipment_id');
            $table->unsignedBigInteger('webhook_event_id')->nullable();
            $table->json('payload');
            $table->unsignedTinyInteger('attempts')->default(0);
            $table->unsignedTinyInteger('max_attempts')->default(3);
            $table->timestamp('next_retry_at')->nullable();
            $table->timestamp('last_attempt_at')->nullable();
            $table->string('last_error', 255)->nullable();
            $table->string('status', 32)->default('pending');
            $table->timestamps();

            $table->index(['status', 'next_retry_at']);
            $table->index('shipment_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('courier_forward_retries');
        Schema::dropIfExists('courier_webhook_events');
    }
};
