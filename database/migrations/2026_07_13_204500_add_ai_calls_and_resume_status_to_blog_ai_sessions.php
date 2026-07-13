<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('blog_ai_sessions', function (Blueprint $table) {
            $table->unsignedInteger('ai_calls')->default(0)->after('total_tokens');
            $table->string('resume_status', 32)->nullable()->after('status');
        });
    }

    public function down(): void
    {
        Schema::table('blog_ai_sessions', function (Blueprint $table) {
            $table->dropColumn(['ai_calls', 'resume_status']);
        });
    }
};
