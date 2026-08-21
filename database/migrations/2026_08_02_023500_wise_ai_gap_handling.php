<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('wise_turns')) {
            return;
        }

        $hasHandledAt = Schema::hasColumn('wise_turns', 'gap_handled_at');
        $hasKnowledgeId = Schema::hasColumn('wise_turns', 'gap_knowledge_id');

        if (! $hasHandledAt || ! $hasKnowledgeId) {
            Schema::table('wise_turns', function (Blueprint $table) use ($hasHandledAt, $hasKnowledgeId) {
                if (! $hasHandledAt) {
                    $table->timestamp('gap_handled_at')->nullable()->after('gap');
                }
                if (! $hasKnowledgeId) {
                    // Column is added above in the same ALTER when missing, so AFTER is safe.
                    $table->unsignedBigInteger('gap_knowledge_id')->nullable()->after('gap_handled_at');
                }
            });
        }

        if (! Schema::hasColumn('wise_turns', 'gap_handled_at')) {
            return;
        }

        $indexes = collect(Schema::getConnection()->select('SHOW INDEX FROM wise_turns'))
            ->pluck('Key_name')
            ->unique()
            ->all();

        if (! in_array('wise_turns_gap_open_idx', $indexes, true)) {
            Schema::table('wise_turns', function (Blueprint $table) {
                $table->index(['gap', 'gap_handled_at'], 'wise_turns_gap_open_idx');
            });
        }
    }

    public function down(): void
    {
        if (! Schema::hasTable('wise_turns')) {
            return;
        }

        $indexes = collect(Schema::getConnection()->select('SHOW INDEX FROM wise_turns'))
            ->pluck('Key_name')
            ->unique()
            ->all();

        Schema::table('wise_turns', function (Blueprint $table) use ($indexes) {
            if (in_array('wise_turns_gap_open_idx', $indexes, true)) {
                $table->dropIndex('wise_turns_gap_open_idx');
            }
        });

        $drop = array_values(array_filter([
            Schema::hasColumn('wise_turns', 'gap_handled_at') ? 'gap_handled_at' : null,
            Schema::hasColumn('wise_turns', 'gap_knowledge_id') ? 'gap_knowledge_id' : null,
        ]));

        if ($drop !== []) {
            Schema::table('wise_turns', function (Blueprint $table) use ($drop) {
                $table->dropColumn($drop);
            });
        }
    }
};
