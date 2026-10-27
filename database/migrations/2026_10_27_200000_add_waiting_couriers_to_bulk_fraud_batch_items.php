<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasColumn('bulk_fraud_batch_items', 'waiting_couriers')) {
            return;
        }

        Schema::table('bulk_fraud_batch_items', function (Blueprint $table) {
            $table->json('waiting_couriers')->nullable()->after('couriers');
            $table->json('fill_report')->nullable()->after('waiting_couriers');
            $table->timestamp('next_fill_at')->nullable()->after('fill_report');

            $table->index('next_fill_at');
        });
    }

    public function down(): void
    {
        if (! Schema::hasColumn('bulk_fraud_batch_items', 'waiting_couriers')) {
            return;
        }

        Schema::table('bulk_fraud_batch_items', function (Blueprint $table) {
            $table->dropIndex(['next_fill_at']);
            $table->dropColumn(['waiting_couriers', 'fill_report', 'next_fill_at']);
        });
    }
};
