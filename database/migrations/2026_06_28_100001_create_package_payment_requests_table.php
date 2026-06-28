<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('package_payment_requests', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('user_id');
            $table->unsignedBigInteger('package_hub_id');
            $table->unsignedBigInteger('user_package_id')->nullable();
            $table->unsignedBigInteger('website_id')->nullable();
            $table->string('domain', 255);
            $table->unsignedInteger('order_limit');
            $table->double('total_amount');
            $table->double('transaction_charge')->default(0);
            $table->string('transaction_method')->nullable();
            $table->string('transaction_id')->nullable();
            $table->string('account_number')->nullable();
            $table->string('status')->default('pending')->comment('pending, approved, cancelled');
            $table->text('note')->nullable();
            $table->unsignedBigInteger('created_by')->nullable();
            $table->unsignedBigInteger('updated_by')->nullable();
            $table->softDeletes();
            $table->timestamps();

            $table->index(['user_id', 'status']);
            $table->index(['domain', 'status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('package_payment_requests');
    }
};
