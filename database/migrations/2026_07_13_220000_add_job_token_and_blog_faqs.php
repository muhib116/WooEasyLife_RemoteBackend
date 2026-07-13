<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('blog_ai_sessions', function (Blueprint $table) {
            $table->string('job_token', 64)->nullable()->after('resume_status');
        });

        Schema::table('blog_posts', function (Blueprint $table) {
            $table->json('faqs_json')->nullable()->after('author_name');
        });
    }

    public function down(): void
    {
        Schema::table('blog_ai_sessions', function (Blueprint $table) {
            $table->dropColumn('job_token');
        });

        Schema::table('blog_posts', function (Blueprint $table) {
            $table->dropColumn('faqs_json');
        });
    }
};
