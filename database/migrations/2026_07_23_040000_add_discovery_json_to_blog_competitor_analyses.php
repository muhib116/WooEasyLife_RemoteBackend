<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('blog_competitor_analyses', function (Blueprint $table) {
            if (! Schema::hasColumn('blog_competitor_analyses', 'discovery_json')) {
                $table->json('discovery_json')->nullable()->after('competitor_urls');
            }
        });
    }

    public function down(): void
    {
        Schema::table('blog_competitor_analyses', function (Blueprint $table) {
            if (Schema::hasColumn('blog_competitor_analyses', 'discovery_json')) {
                $table->dropColumn('discovery_json');
            }
        });
    }
};
