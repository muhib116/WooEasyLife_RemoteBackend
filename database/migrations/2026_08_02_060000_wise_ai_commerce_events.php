<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('wise_commerce_events')) {
            return;
        }

        Schema::create('wise_commerce_events', function (Blueprint $table) {
            $table->id();
            $table->foreignId('wise_api_key_id')->index();
            $table->string('event_type', 40)->index();
            $table->string('conversation_id')->nullable()->index();
            $table->unsignedBigInteger('wise_turn_id')->nullable()->index();
            $table->string('external_order_id', 191)->nullable()->index();
            $table->string('platform', 40)->nullable()->index();
            $table->decimal('amount', 14, 2)->nullable();
            $table->string('currency', 8)->nullable();
            $table->timestamp('occurred_at')->index();
            $table->string('idempotency_key', 191);
            $table->json('meta')->nullable();
            $table->timestamps();

            $table->unique(['wise_api_key_id', 'idempotency_key'], 'wise_commerce_events_key_idem');
            $table->index(['wise_api_key_id', 'conversation_id', 'event_type'], 'wise_commerce_events_attr');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('wise_commerce_events');
    }
};
