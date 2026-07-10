<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('subscription_inquiries', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('user_id')->nullable();
            $table->unsignedBigInteger('package_hub_id');
            $table->string('domain', 191);
            $table->string('customer_name')->nullable();
            $table->string('email');
            $table->string('contact_number', 30);
            $table->string('whatsapp_number', 30);
            $table->text('address');
            $table->unsignedInteger('order_limit')->default(0);
            $table->double('total_amount')->default(0);
            $table->double('transaction_charge')->default(0);
            $table->string('transaction_method', 64)->nullable();
            $table->string('transaction_id', 128)->nullable();
            $table->string('account_number', 64)->nullable();
            $table->text('note')->nullable();
            $table->string('status', 32)->default('pending');
            $table->string('source', 64)->default('landing_pricing');
            $table->unsignedBigInteger('package_payment_request_id')->nullable();
            $table->timestamps();

            $table->index(['status', 'created_at']);
            $table->index('domain');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('subscription_inquiries');
    }
};
