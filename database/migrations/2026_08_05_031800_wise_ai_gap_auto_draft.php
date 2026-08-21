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

        if (! Schema::hasColumn('wise_turns', 'gap_auto_draft_id')) {
            $after = Schema::hasColumn('wise_turns', 'gap_knowledge_id')
                ? 'gap_knowledge_id'
                : (Schema::hasColumn('wise_turns', 'gap_handled_at') ? 'gap_handled_at' : 'gap');

            Schema::table('wise_turns', function (Blueprint $table) use ($after) {
                $table->unsignedBigInteger('gap_auto_draft_id')->nullable()->after($after);
            });
        }

        $indexes = collect(Schema::getConnection()->select('SHOW INDEX FROM wise_turns'))
            ->pluck('Key_name')
            ->unique()
            ->all();

        if (! in_array('wise_turns_gap_auto_draft_idx', $indexes, true)) {
            Schema::table('wise_turns', function (Blueprint $table) {
                $table->index('gap_auto_draft_id', 'wise_turns_gap_auto_draft_idx');
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
            if (in_array('wise_turns_gap_auto_draft_idx', $indexes, true)) {
                $table->dropIndex('wise_turns_gap_auto_draft_idx');
            }
            if (Schema::hasColumn('wise_turns', 'gap_auto_draft_id')) {
                $table->dropColumn('gap_auto_draft_id');
            }
        });
    }
};
