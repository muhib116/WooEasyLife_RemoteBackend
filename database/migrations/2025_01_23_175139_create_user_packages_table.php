<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('user_packages', function (Blueprint $table) {
            $table->id();
            $table->string('title')->nullable();
            $table->text('description')->nullable();
            $table->text('domain')->nullable();
            $table->unsignedBigInteger('user_id');
            $table->unsignedBigInteger('package_hub_id');
            $table->integer('total_order_can_handle')->default(100);
            $table->integer('total_order_handled')->default(0);
            $table->integer('remaining_order')->default(0);
            $table->integer('per_order_rate');
            $table->integer('total_cost');
            $table->integer('transaction_charge');
            $table->string('transaction_method')->nullable();
            $table->string('transaction_id')->nullable();
            $table->string('transaction_number')->nullable();
            $table->boolean('is_active')->default(false);
            $table->text('note')->nullable();
            $table->unsignedBigInteger('created_by')->nullable();
            $table->unsignedBigInteger('updated_by')->nullable();
            $table->softDeletes();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('user_packages');
    }
};
