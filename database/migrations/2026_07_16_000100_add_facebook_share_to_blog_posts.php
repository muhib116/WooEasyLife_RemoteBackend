<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('blog_posts', function (Blueprint $table) {
            $table->string('facebook_post_id')->nullable()->after('ai_run_id');
            $table->timestamp('facebook_shared_at')->nullable()->after('facebook_post_id');
        });
    }

    public function down(): void
    {
        Schema::table('blog_posts', function (Blueprint $table) {
            $table->dropColumn(['facebook_post_id', 'facebook_shared_at']);
        });
    }
};
