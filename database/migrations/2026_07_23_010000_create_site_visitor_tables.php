<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('site_visitor_events')) {
            Schema::create('site_visitor_events', function (Blueprint $table) {
                $table->id();
                $table->string('path', 500);
                $table->string('event_type', 32);
                $table->string('visitor_hash', 64)->nullable();
                $table->string('session_hash', 64)->nullable();
                $table->string('referrer_host', 120)->nullable();
                $table->string('utm_source', 120)->nullable();
                $table->string('utm_medium', 120)->nullable();
                $table->string('utm_campaign', 120)->nullable();
                $table->string('utm_content', 120)->nullable();
                $table->string('utm_term', 120)->nullable();
                $table->string('source_channel', 32)->nullable()->index();
                $table->string('device_type', 16)->nullable();
                $table->string('country', 8)->nullable();
                $table->unsignedTinyInteger('scroll_pct')->nullable();
                $table->unsignedInteger('engaged_ms')->nullable();
                $table->string('cta_label', 120)->nullable();
                $table->string('action_name', 120)->nullable();
                $table->json('meta')->nullable();
                $table->timestamps();

                $table->index('created_at');
                $table->index(['path', 'event_type', 'created_at']);
                $table->index(['visitor_hash', 'created_at']);
            });
        }

        if (! Schema::hasTable('site_visitor_daily_stats')) {
            Schema::create('site_visitor_daily_stats', function (Blueprint $table) {
                $table->id();
                $table->date('date');
                $table->string('path', 500);
                $table->unsignedBigInteger('pageviews')->default(0);
                $table->unsignedBigInteger('unique_visitors')->default(0);
                $table->unsignedBigInteger('sessions')->default(0);
                $table->unsignedInteger('avg_engaged_ms')->default(0);
                $table->unsignedBigInteger('scroll_50_count')->default(0);
                $table->unsignedBigInteger('cta_clicks')->default(0);
                $table->timestamps();

                $table->unique(['date', 'path']);
                $table->index('date');
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('site_visitor_daily_stats');
        Schema::dropIfExists('site_visitor_events');
    }
};
