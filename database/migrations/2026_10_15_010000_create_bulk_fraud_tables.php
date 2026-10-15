<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('bulk_fraud_batches', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('name', 80);
            $table->string('status', 20)->default('running');
            $table->unsignedInteger('submitted_count')->default(0);
            $table->unsignedInteger('unique_count')->default(0);
            $table->unsignedInteger('duplicates_removed')->default(0);
            $table->timestamps();

            $table->index(['user_id', 'created_at']);
        });

        Schema::create('bulk_fraud_daily_results', function (Blueprint $table) {
            $table->id();
            $table->string('phone', 20);
            $table->date('checked_on');
            $table->string('status', 20);
            $table->unsignedInteger('total_orders')->default(0);
            $table->unsignedInteger('received')->default(0);
            $table->unsignedInteger('cancelled')->default(0);
            $table->unsignedInteger('partial')->default(0);
            $table->unsignedSmallInteger('success_rate')->nullable();
            $table->string('risk', 20)->nullable();
            $table->json('couriers');
            $table->unsignedTinyInteger('attempts')->default(1);
            $table->timestamps();

            $table->unique(['phone', 'checked_on']);
        });

        Schema::create('bulk_fraud_batch_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('batch_id')->constrained('bulk_fraud_batches')->cascadeOnDelete();
            $table->unsignedInteger('position');
            $table->string('phone', 32);
            $table->string('status', 20)->default('pending');
            $table->string('source', 20)->nullable();
            $table->foreignId('daily_result_id')->nullable()->constrained('bulk_fraud_daily_results')->nullOnDelete();
            $table->unsignedTinyInteger('attempts')->default(0);
            $table->unsignedInteger('total_orders')->default(0);
            $table->unsignedInteger('received')->default(0);
            $table->unsignedInteger('cancelled')->default(0);
            $table->unsignedInteger('partial')->default(0);
            $table->unsignedSmallInteger('success_rate')->nullable();
            $table->string('risk', 20)->nullable();
            $table->json('couriers')->nullable();
            $table->timestamps();

            $table->index(['batch_id', 'status', 'position']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('bulk_fraud_batch_items');
        Schema::dropIfExists('bulk_fraud_daily_results');
        Schema::dropIfExists('bulk_fraud_batches');
    }
};
