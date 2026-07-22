<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('blog_clusters', function (Blueprint $table) {
            if (! Schema::hasColumn('blog_clusters', 'landing_json')) {
                $table->json('landing_json')->nullable()->after('seed_queries');
            }
            if (! Schema::hasColumn('blog_clusters', 'detect_needles')) {
                $table->json('detect_needles')->nullable()->after('landing_json');
            }
        });

        $landings = config('blog_ai.cluster_landing', []);
        $needles = config('blog_ai.cluster_detect_needles', []);

        if (! is_array($landings)) {
            $landings = [];
        }
        if (! is_array($needles)) {
            $needles = [];
        }

        // Only backfill empty values so re-running never wipes admin edits.
        $rows = DB::table('blog_clusters')->get(['id', 'key', 'landing_json', 'detect_needles']);
        foreach ($rows as $row) {
            $landing = is_array($landings[$row->key] ?? null) ? $landings[$row->key] : null;
            $detect = is_array($needles[$row->key] ?? null) ? array_values($needles[$row->key]) : [];

            $payload = [];
            if ($row->landing_json === null && $landing) {
                $payload['landing_json'] = json_encode($landing, JSON_UNESCAPED_UNICODE);
            }
            if ($row->detect_needles === null && $detect !== []) {
                $payload['detect_needles'] = json_encode($detect, JSON_UNESCAPED_UNICODE);
            }
            if ($payload === []) {
                continue;
            }

            $payload['updated_at'] = now();
            DB::table('blog_clusters')->where('id', $row->id)->update($payload);
        }
    }

    public function down(): void
    {
        Schema::table('blog_clusters', function (Blueprint $table) {
            if (Schema::hasColumn('blog_clusters', 'detect_needles')) {
                $table->dropColumn('detect_needles');
            }
            if (Schema::hasColumn('blog_clusters', 'landing_json')) {
                $table->dropColumn('landing_json');
            }
        });
    }
};
