<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('courier_webhook_events', function (Blueprint $table) {
            $table->string('idempotency_key', 191)->nullable()->after('event_type');
            $table->unique(['partner', 'idempotency_key'], 'courier_webhook_events_partner_idem');
            $table->index(['partner', 'forward_status', 'created_at'], 'courier_webhook_events_status_created');
        });
    }

    public function down(): void
    {
        Schema::table('courier_webhook_events', function (Blueprint $table) {
            $table->dropIndex('courier_webhook_events_status_created');
            $table->dropUnique('courier_webhook_events_partner_idem');
            $table->dropColumn('idempotency_key');
        });
    }
};
