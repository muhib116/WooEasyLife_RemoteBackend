<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('platform_customers', function (Blueprint $table) {
            $table->id();
            $table->char('phone_normalized', 11);
            $table->string('latest_name')->nullable();
            $table->text('latest_address')->nullable();
            $table->timestamp('first_seen_at');
            $table->timestamp('last_seen_at');
            $table->timestamp('last_order_at')->nullable();
            $table->timestamps();

            $table->unique('phone_normalized');
            $table->index('last_seen_at');
            $table->index('last_order_at');
        });

        Schema::create('platform_orders', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('platform_customer_id');
            $table->unsignedBigInteger('access_token_id');
            $table->unsignedBigInteger('user_id');
            $table->unsignedInteger('wc_order_id');
            $table->string('external_ref', 64)->nullable();
            $table->string('current_status', 32);
            $table->timestamp('status_changed_at');
            $table->string('courier_partner', 16)->nullable();
            $table->string('consignment_id', 128)->nullable();
            $table->unsignedBigInteger('courier_shipment_id')->nullable();
            $table->decimal('order_amount', 12, 2)->nullable();
            $table->char('currency', 3)->default('BDT');
            $table->string('product_category', 64)->nullable();
            $table->string('source', 32);
            $table->timestamp('fraud_checked_at')->nullable();
            $table->timestamps();

            $table->unique(['access_token_id', 'wc_order_id']);
            $table->index(['platform_customer_id', 'current_status']);
            $table->index(['platform_customer_id', 'created_at']);
            $table->index('consignment_id');
            $table->index('courier_shipment_id');

            $table->foreign('platform_customer_id')
                ->references('id')
                ->on('platform_customers')
                ->cascadeOnDelete();
            $table->foreign('courier_shipment_id')
                ->references('id')
                ->on('courier_shipments')
                ->nullOnDelete();
        });

        Schema::create('merchant_order_details', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('platform_order_id');
            $table->unsignedBigInteger('access_token_id');
            $table->string('customer_name')->nullable();
            $table->text('customer_address')->nullable();
            $table->string('customer_email')->nullable();
            $table->string('product_title', 512)->nullable();
            $table->string('product_sku', 128)->nullable();
            $table->unsignedSmallInteger('quantity')->default(1);
            $table->json('line_items')->nullable();
            $table->timestamps();

            $table->unique('platform_order_id');
            $table->index('access_token_id');

            $table->foreign('platform_order_id')
                ->references('id')
                ->on('platform_orders')
                ->cascadeOnDelete();
        });

        Schema::create('order_status_events', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('platform_order_id');
            $table->unsignedBigInteger('platform_customer_id');
            $table->string('from_status', 32)->nullable();
            $table->string('to_status', 32);
            $table->string('source', 32);
            $table->unsignedBigInteger('access_token_id')->nullable();
            $table->unsignedBigInteger('courier_webhook_event_id')->nullable();
            $table->string('partner', 16)->nullable();
            $table->string('raw_status', 64)->nullable();
            $table->timestamp('occurred_at');
            $table->string('idempotency_key', 128);
            $table->timestamp('created_at');

            $table->unique('idempotency_key');
            $table->index(['platform_order_id', 'occurred_at']);
            $table->index(['platform_customer_id', 'occurred_at']);

            $table->foreign('platform_order_id')
                ->references('id')
                ->on('platform_orders')
                ->cascadeOnDelete();
            $table->foreign('platform_customer_id')
                ->references('id')
                ->on('platform_customers')
                ->cascadeOnDelete();
            $table->foreign('courier_webhook_event_id')
                ->references('id')
                ->on('courier_webhook_events')
                ->nullOnDelete();
        });

        Schema::create('platform_customer_stats', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('platform_customer_id');
            $table->char('phone_normalized', 11);
            $table->json('counts');
            $table->json('rates');
            $table->unsignedInteger('total_orders')->default(0);
            $table->unsignedSmallInteger('total_merchants')->default(0);
            $table->decimal('total_revenue', 14, 2)->default(0);
            $table->decimal('delivery_rate', 5, 4)->nullable();
            $table->decimal('return_rate', 5, 4)->nullable();
            $table->string('risk_tier', 16)->nullable();
            $table->timestamp('last_order_at')->nullable();
            $table->timestamp('stats_computed_at');
            $table->unsignedInteger('version')->default(0);
            $table->timestamps();

            $table->unique('platform_customer_id');
            $table->unique('phone_normalized');
            $table->index('risk_tier');
            $table->index('delivery_rate');
            $table->index('last_order_at');

            $table->foreign('platform_customer_id')
                ->references('id')
                ->on('platform_customers')
                ->cascadeOnDelete();
        });

        Schema::create('merchant_customer_stats', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('platform_customer_id');
            $table->unsignedBigInteger('access_token_id');
            $table->unsignedBigInteger('user_id');
            $table->char('phone_normalized', 11);
            $table->json('counts');
            $table->unsignedInteger('total_orders')->default(0);
            $table->timestamp('last_order_at')->nullable();
            $table->timestamp('stats_computed_at');
            $table->timestamps();

            $table->unique(['access_token_id', 'platform_customer_id'], 'mcs_store_customer_unique');
            $table->index(['phone_normalized', 'access_token_id']);

            $table->foreign('platform_customer_id')
                ->references('id')
                ->on('platform_customers')
                ->cascadeOnDelete();
        });

        Schema::create('courier_customer_snapshots', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('platform_customer_id');
            $table->char('phone_normalized', 11);
            $table->string('courier', 16);
            $table->unsignedInteger('total_order')->default(0);
            $table->unsignedInteger('confirmed')->default(0);
            $table->unsignedInteger('cancel')->default(0);
            $table->string('success_rate', 32)->nullable();
            $table->string('customer_rating', 64)->nullable();
            $table->unsignedSmallInteger('frauds_count')->default(0);
            $table->json('raw_report')->nullable();
            $table->timestamp('fetched_at');
            $table->unsignedBigInteger('source_access_token_id')->nullable();
            $table->timestamps();

            $table->unique(['platform_customer_id', 'courier']);
            $table->index('phone_normalized');

            $table->foreign('platform_customer_id')
                ->references('id')
                ->on('platform_customers')
                ->cascadeOnDelete();
        });

        Schema::create('courier_fraud_reports', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('platform_customer_id');
            $table->char('phone_normalized', 11);
            $table->string('courier', 16)->default('steadfast');
            $table->string('reporter_name')->nullable();
            $table->text('details');
            $table->string('consignment_id', 128)->nullable();
            $table->timestamp('reported_at')->nullable();
            $table->char('fingerprint', 64);
            $table->unsignedBigInteger('source_access_token_id')->nullable();
            $table->json('raw_payload')->nullable();
            $table->timestamp('first_seen_at');
            $table->timestamp('last_seen_at');
            $table->timestamps();

            $table->unique('fingerprint');
            $table->index(['platform_customer_id', 'reported_at']);
            $table->index('phone_normalized');

            $table->foreign('platform_customer_id')
                ->references('id')
                ->on('platform_customers')
                ->cascadeOnDelete();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('courier_fraud_reports');
        Schema::dropIfExists('courier_customer_snapshots');
        Schema::dropIfExists('merchant_customer_stats');
        Schema::dropIfExists('platform_customer_stats');
        Schema::dropIfExists('order_status_events');
        Schema::dropIfExists('merchant_order_details');
        Schema::dropIfExists('platform_orders');
        Schema::dropIfExists('platform_customers');
    }
};
