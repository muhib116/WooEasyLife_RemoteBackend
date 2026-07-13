<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('blog_posts')) {
            return;
        }

        // Repair CMS rows that were marked published but never got a timestamp
        // (they 404'd when the public query required published_at).
        DB::table('blog_posts')
            ->where('status', 'published')
            ->whereNull('published_at')
            ->update([
                'published_at' => DB::raw('COALESCE(created_at, NOW())'),
            ]);
    }

    public function down(): void
    {
        // Irreversible data repair.
    }
};
