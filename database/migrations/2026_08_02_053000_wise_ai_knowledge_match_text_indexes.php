<?php

use App\WiseAi\Knowledge\KnowledgeLookup;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('wise_knowledge_items')) {
            return;
        }

        $hasMatchText = Schema::hasColumn('wise_knowledge_items', 'match_text');
        $hasScope = Schema::hasColumn('wise_knowledge_items', 'scope');
        $hasExternalId = Schema::hasColumn('wise_knowledge_items', 'external_id');

        if (! $hasMatchText) {
            Schema::table('wise_knowledge_items', function (Blueprint $table) {
                $table->mediumText('match_text')->nullable()->after('keywords');
            });
            $hasMatchText = true;
        }

        $indexes = collect(DB::select('SHOW INDEX FROM wise_knowledge_items'))
            ->pluck('Key_name')
            ->unique()
            ->all();

        Schema::table('wise_knowledge_items', function (Blueprint $table) use ($indexes, $hasScope, $hasExternalId) {
            if (! in_array('wise_knowledge_key_status_type', $indexes, true)) {
                $table->index(['wise_api_key_id', 'status', 'type'], 'wise_knowledge_key_status_type');
            }
            if ($hasScope && ! in_array('wise_knowledge_status_scope', $indexes, true)) {
                $table->index(['status', 'scope'], 'wise_knowledge_status_scope');
            }
            if ($hasExternalId && ! in_array('wise_knowledge_ext_status_type', $indexes, true)) {
                $table->index(['external_id', 'status', 'type'], 'wise_knowledge_ext_status_type');
            }
        });

        if (! $hasMatchText) {
            return;
        }

        // Backfill denormalized match blob for SQL candidate prefilter.
        DB::table('wise_knowledge_items')->orderBy('id')->chunkById(200, function ($rows) {
            foreach ($rows as $row) {
                $keywords = json_decode((string) ($row->keywords ?? '[]'), true);
                if (! is_array($keywords)) {
                    $keywords = [];
                }
                $match = KnowledgeLookup::buildMatchText(
                    (string) ($row->title ?? ''),
                    (string) ($row->question ?? ''),
                    $keywords,
                );
                DB::table('wise_knowledge_items')
                    ->where('id', $row->id)
                    ->update(['match_text' => $match]);
            }
        });
    }

    public function down(): void
    {
        if (! Schema::hasTable('wise_knowledge_items')) {
            return;
        }

        $indexes = collect(DB::select('SHOW INDEX FROM wise_knowledge_items'))
            ->pluck('Key_name')
            ->unique()
            ->all();
        $hasMatchText = Schema::hasColumn('wise_knowledge_items', 'match_text');

        Schema::table('wise_knowledge_items', function (Blueprint $table) use ($indexes, $hasMatchText) {
            foreach ([
                'wise_knowledge_key_status_type',
                'wise_knowledge_status_scope',
                'wise_knowledge_ext_status_type',
            ] as $index) {
                if (in_array($index, $indexes, true)) {
                    $table->dropIndex($index);
                }
            }

            if ($hasMatchText) {
                $table->dropColumn('match_text');
            }
        });
    }
};
