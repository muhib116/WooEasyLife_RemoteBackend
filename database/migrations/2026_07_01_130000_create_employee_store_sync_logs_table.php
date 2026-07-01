<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('employee_store_sync_logs', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('merchant_user_id');
            $table->unsignedBigInteger('merchant_employee_id');
            $table->unsignedBigInteger('website_id');
            $table->string('domain')->nullable();
            $table->string('action', 20);
            $table->boolean('success')->default(false);
            $table->text('message')->nullable();
            $table->unsignedSmallInteger('http_status')->nullable();
            $table->unsignedTinyInteger('attempt_count')->default(1);
            $table->unsignedTinyInteger('max_attempts')->default(3);
            $table->boolean('retry_scheduled')->default(false);
            $table->json('payload')->nullable();
            $table->timestamp('last_attempted_at')->nullable();
            $table->timestamp('resolved_at')->nullable();
            $table->timestamps();

            $table->index(['merchant_user_id', 'success', 'resolved_at'], 'employee_sync_logs_merchant_status_idx');
            $table->index(['merchant_employee_id', 'website_id', 'action'], 'employee_sync_logs_employee_store_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('employee_store_sync_logs');
    }
};
