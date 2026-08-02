<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Ensure discovery indexes exist without failing on retry / partial runs.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('wise_language_reviews')) {
            return;
        }

        $existing = collect(DB::select('SHOW INDEX FROM wise_language_reviews'))
            ->pluck('Key_name')
            ->unique()
            ->all();

        if (! in_array('wise_lang_review_rank_idx', $existing, true)) {
            DB::statement('ALTER TABLE wise_language_reviews ADD INDEX wise_lang_review_rank_idx (status, rank_score)');
        }
        if (! in_array('wise_lang_review_kind_idx', $existing, true)) {
            DB::statement('ALTER TABLE wise_language_reviews ADD INDEX wise_lang_review_kind_idx (kind, status)');
        }
    }

    public function down(): void
    {
        // Keep indexes — safe for protocol.
    }
};
