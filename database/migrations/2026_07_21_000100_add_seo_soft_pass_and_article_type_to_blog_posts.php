<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('blog_posts', function (Blueprint $table) {
            if (! Schema::hasColumn('blog_posts', 'seo_soft_pass')) {
                $table->boolean('seo_soft_pass')->default(false)->after('ai_run_id');
            }
            if (! Schema::hasColumn('blog_posts', 'article_type')) {
                $table->string('article_type', 32)->default('howto')->after('cluster');
            }
        });
    }

    public function down(): void
    {
        Schema::table('blog_posts', function (Blueprint $table) {
            if (Schema::hasColumn('blog_posts', 'seo_soft_pass')) {
                $table->dropColumn('seo_soft_pass');
            }
            if (Schema::hasColumn('blog_posts', 'article_type')) {
                $table->dropColumn('article_type');
            }
        });
    }
};
