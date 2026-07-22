<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('site_visitor_events')) {
            return;
        }

        Schema::table('site_visitor_events', function (Blueprint $table) {
            $table->index(['event_type', 'created_at'], 'site_visitor_events_event_created_idx');
        });
    }

    public function down(): void
    {
        if (! Schema::hasTable('site_visitor_events')) {
            return;
        }

        Schema::table('site_visitor_events', function (Blueprint $table) {
            $table->dropIndex('site_visitor_events_event_created_idx');
        });
    }
};
