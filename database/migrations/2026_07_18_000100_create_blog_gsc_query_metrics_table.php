<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('blog_gsc_query_metrics')) {
            return;
        }

        Schema::create('blog_gsc_query_metrics', function (Blueprint $table) {
            $table->id();
            $table->string('pair_hash', 64)->unique();
            $table->string('query', 500);
            $table->string('page_url', 500);
            $table->string('slug', 255)->nullable()->index();
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
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('blog_gsc_query_metrics');
    }
};
