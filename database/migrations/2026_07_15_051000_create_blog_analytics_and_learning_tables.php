<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('blog_content_events')) {
            Schema::create('blog_content_events', function (Blueprint $table) {
                $table->id();
                $table->string('slug', 255)->index();
                $table->foreignId('blog_post_id')->nullable()->constrained('blog_posts')->nullOnDelete();
                $table->string('event_type', 32)->index(); // view | cta_click | scroll_depth
                $table->string('visitor_hash', 64)->nullable()->index();
                $table->string('session_hash', 64)->nullable()->index();
                $table->string('cta_label', 120)->nullable();
                $table->string('referrer_host', 120)->nullable();
                $table->unsignedTinyInteger('scroll_pct')->nullable();
                $table->timestamps();

                $table->index(['slug', 'event_type', 'created_at']);
            });
        }

        if (! Schema::hasTable('blog_post_analytics')) {
            Schema::create('blog_post_analytics', function (Blueprint $table) {
                $table->id();
                $table->string('slug', 255)->unique();
                $table->foreignId('blog_post_id')->nullable()->constrained('blog_posts')->nullOnDelete();
                $table->string('title')->nullable();
                $table->string('focus_keyword')->nullable()->index();
                $table->string('cluster', 64)->nullable()->index();
                $table->string('locale', 5)->default('bn')->index();
                $table->unsignedBigInteger('views_total')->default(0);
                $table->unsignedBigInteger('views_7d')->default(0);
                $table->unsignedBigInteger('views_28d')->default(0);
                $table->unsignedBigInteger('unique_visitors_28d')->default(0);
                $table->unsignedBigInteger('cta_clicks_total')->default(0);
                $table->unsignedBigInteger('cta_clicks_28d')->default(0);
                $table->unsignedInteger('gsc_clicks_28d')->default(0);
                $table->unsignedInteger('gsc_impressions_28d')->default(0);
                $table->decimal('gsc_ctr_28d', 8, 4)->nullable();
                $table->decimal('gsc_position_28d', 8, 2)->nullable();
                $table->decimal('engagement_score', 10, 2)->default(0)->index();
                $table->json('top_cta_labels')->nullable();
                $table->json('meta_json')->nullable();
                $table->timestamp('last_viewed_at')->nullable();
                $table->timestamp('metrics_refreshed_at')->nullable();
                $table->timestamps();
            });
        }

        if (! Schema::hasTable('blog_learning_insights')) {
            Schema::create('blog_learning_insights', function (Blueprint $table) {
                $table->id();
                $table->string('scope', 32)->default('global')->index();
                $table->json('payload_json');
                $table->text('summary_bn')->nullable();
                $table->unsignedInteger('posts_analyzed')->default(0);
                $table->unsignedInteger('events_analyzed')->default(0);
                $table->timestamp('generated_at')->index();
                $table->timestamps();
            });
        }

        if (Schema::hasTable('blog_posts') && ! Schema::hasColumn('blog_posts', 'cluster')) {
            Schema::table('blog_posts', function (Blueprint $table) {
                $table->string('cluster', 64)->nullable()->after('locale')->index();
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasTable('blog_posts') && Schema::hasColumn('blog_posts', 'cluster')) {
            Schema::table('blog_posts', function (Blueprint $table) {
                $table->dropColumn('cluster');
            });
        }

        Schema::dropIfExists('blog_learning_insights');
        Schema::dropIfExists('blog_post_analytics');
        Schema::dropIfExists('blog_content_events');
    }
};
