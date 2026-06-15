<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('courier_accounts', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('user_id');
            $table->unsignedBigInteger('courier_configuration_id')->nullable();
            $table->string('partner', 32);
            $table->string('environment', 16)->default('live');
            $table->string('credential_hash', 64);
            $table->string('webhook_verify_secret', 255)->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestamp('retired_at')->nullable();
            $table->timestamps();

            $table->unique(['partner', 'credential_hash', 'environment'], 'courier_accounts_unique_hash');
            $table->index(['user_id', 'partner']);
        });

        Schema::create('license_courier_accounts', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('access_token_id');
            $table->unsignedBigInteger('courier_account_id');
            $table->boolean('is_current')->default(true);
            $table->timestamp('assigned_at')->nullable();
            $table->timestamp('revoked_at')->nullable();
            $table->timestamps();

            $table->index(['access_token_id', 'is_current']);
            $table->index('courier_account_id');
        });

        Schema::create('courier_shipments', function (Blueprint $table) {
            $table->id();
            $table->string('partner', 32);
            $table->string('environment', 16)->default('live');
            $table->string('consignment_id', 128);
            $table->string('invoice', 128)->nullable();
            $table->unsignedInteger('wc_order_id')->nullable();
            $table->unsignedBigInteger('user_id');
            $table->unsignedBigInteger('access_token_id');
            $table->string('site_url', 512);
            $table->string('site_domain', 255);
            $table->unsignedBigInteger('courier_account_id');
            $table->unsignedBigInteger('courier_configuration_id')->nullable();
            $table->string('status', 64)->nullable();
            $table->timestamp('last_webhook_at')->nullable();
            $table->timestamps();

            $table->unique(['partner', 'consignment_id'], 'courier_shipments_partner_consignment');
            $table->index(['access_token_id', 'wc_order_id']);
            $table->index('courier_account_id');
        });

        Schema::create('courier_hub_tokens', function (Blueprint $table) {
            $table->id();
            $table->string('partner', 32)->unique();
            $table->string('token', 128);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('courier_hub_tokens');
        Schema::dropIfExists('courier_shipments');
        Schema::dropIfExists('license_courier_accounts');
        Schema::dropIfExists('courier_accounts');
    }
};
