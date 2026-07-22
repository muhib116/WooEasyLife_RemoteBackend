<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('site_visitor_events') && ! Schema::hasColumn('site_visitor_events', 'search_keyword')) {
            Schema::table('site_visitor_events', function (Blueprint $table) {
                $table->string('search_keyword', 255)->nullable()->after('utm_term')->index();
            });
        }

        if (! Schema::hasTable('site_gsc_query_metrics')) {
            Schema::create('site_gsc_query_metrics', function (Blueprint $table) {
                $table->id();
                $table->string('pair_hash', 64)->unique();
                $table->string('query', 500)->index();
                $table->string('page_url', 500);
                $table->string('path', 500)->nullable()->index();
                $table->unsignedInteger('clicks_28d')->default(0);
                $table->unsignedInteger('impressions_28d')->default(0);
                $table->decimal('ctr_28d', 8, 4)->nullable();
                $table->decimal('position_28d', 8, 2)->nullable();
                $table->string('bucket', 32)->default('other')->index();
                $table->decimal('opportunity_score', 10, 2)->default(0)->index();
                $table->string('improvement_hint', 500)->nullable();
                $table->timestamp('metrics_refreshed_at')->nullable()->index();
                $table->timestamps();

                $table->index(['bucket', 'opportunity_score']);
                $table->index(['path', 'impressions_28d']);
            });
        }

        if (! Schema::hasTable('site_gsc_page_metrics')) {
            Schema::create('site_gsc_page_metrics', function (Blueprint $table) {
                $table->id();
                $table->string('page_url', 500);
                $table->string('path', 500)->unique();
                $table->unsignedInteger('clicks_28d')->default(0);
                $table->unsignedInteger('impressions_28d')->default(0);
                $table->decimal('ctr_28d', 8, 4)->nullable();
                $table->decimal('position_28d', 8, 2)->nullable();
                $table->timestamp('metrics_refreshed_at')->nullable()->index();
                $table->timestamps();

                $table->index(['impressions_28d', 'clicks_28d']);
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('site_gsc_page_metrics');
        Schema::dropIfExists('site_gsc_query_metrics');

        if (Schema::hasTable('site_visitor_events') && Schema::hasColumn('site_visitor_events', 'search_keyword')) {
            Schema::table('site_visitor_events', function (Blueprint $table) {
                $table->dropColumn('search_keyword');
            });
        }
    }
};
