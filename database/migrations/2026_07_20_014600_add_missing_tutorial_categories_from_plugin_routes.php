<?php

use App\Models\TutorialCategory;
use App\Services\TutorialService;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('tutorial_categories')) {
            return;
        }

        $existing = DB::table('tutorial_categories')->pluck('key')->all();
        $maxSort = (int) DB::table('tutorial_categories')->max('sort_order');
        $now = now();
        $rows = [];

        foreach (TutorialCategory::KNOWN_KEYS as $index => $key) {
            if (in_array($key, $existing, true)) {
                continue;
            }

            $rows[] = [
                'key' => $key,
                'label' => TutorialCategory::KEY_LABELS[$key] ?? $key,
                'sort_order' => $maxSort + 1 + count($rows),
                'created_at' => $now,
                'updated_at' => $now,
            ];
        }

        if ($rows !== []) {
            DB::table('tutorial_categories')->insert($rows);
        }

        // Keep known labels in sync for existing rows.
        foreach (TutorialCategory::KEY_LABELS as $key => $label) {
            DB::table('tutorial_categories')
                ->where('key', $key)
                ->where('label', '!=', $label)
                ->update(['label' => $label, 'updated_at' => $now]);
        }

        if (app()->bound(TutorialService::class)) {
            app(TutorialService::class)->forgetCache();
        }
    }

    public function down(): void
    {
        if (! Schema::hasTable('tutorial_categories')) {
            return;
        }

        $addedKeys = [
            'openAi',
            'subscription',
            'subscriptionTransactionHistory',
            'employees',
            'integrationTab',
            'securitySettings',
            'securityCheckout',
            'securityFraud',
            'developerTools',
            'developerToolsDeveloper',
            'marketingToolsTab',
            'databaseMigration',
            'databaseIndexing',
            'orderStatusLogs',
            'databaseBackup',
            'metaAiBot',
            'connectApp',
        ];

        $ids = DB::table('tutorial_categories')
            ->whereIn('key', $addedKeys)
            ->pluck('id');

        if ($ids->isEmpty()) {
            return;
        }

        $usedIds = DB::table('tutorial_videos')
            ->whereIn('tutorial_category_id', $ids)
            ->pluck('tutorial_category_id')
            ->unique()
            ->all();

        $deletable = $ids->reject(fn ($id) => in_array($id, $usedIds, true))->all();

        if ($deletable !== []) {
            DB::table('tutorial_categories')->whereIn('id', $deletable)->delete();
        }
    }
};
