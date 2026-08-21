<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Downgrade previously auto-published platform/regional seed rows to draft.
 * Merchant-owned knowledge and non-seed rows are untouched.
 *
 * Idempotent: no-op if table missing; re-run only touches still-published seed rows.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('wise_knowledge_items')) {
            return;
        }

        $keys = ['platform_script_catalog', 'regional_knowledge_seeder'];

        foreach ($keys as $seededFrom) {
            // MariaDB-safe JSON path (same as Laravel meta->seeded_from).
            DB::table('wise_knowledge_items')
                ->whereNull('wise_api_key_id')
                ->where('status', 'published')
                ->whereRaw(
                    "JSON_UNQUOTE(JSON_EXTRACT(meta, '$.seeded_from')) = ?",
                    [$seededFrom]
                )
                ->update([
                    'status' => 'draft',
                    'updated_at' => now(),
                ]);
        }
    }

    public function down(): void
    {
        // Irreversible on purpose — do not re-auto-publish without human review.
    }
};
