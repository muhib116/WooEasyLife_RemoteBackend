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
        Schema::create('sms_recharges', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('user_id')->nullable();
            $table->unsignedBigInteger('created_by')->nullable();
            $table->unsignedBigInteger('updated_by')->nullable();
            $table->double('total_amount')->nullable();
            $table->double('transaction_charge')->nullable();
            $table->string('transaction_method')->nullable();
            $table->string('transaction_id')->nullable();
            $table->string('account_number')->nullable();
            $table->string('domain')->nullable();
            $table->string('status')->default('pending')->comment('pending, approved, cancelled, fake');
            $table->softDeletes();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('sms_recharges');
    }
};
