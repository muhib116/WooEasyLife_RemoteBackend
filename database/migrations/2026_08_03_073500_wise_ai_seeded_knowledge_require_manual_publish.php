<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Downgrade previously auto-published platform/regional seed rows to draft.
 * Merchant-owned knowledge and non-seed rows are untouched.
 */
return new class extends Migration
{
    public function up(): void
    {
        $keys = ['platform_script_catalog', 'regional_knowledge_seeder'];

        foreach ($keys as $seededFrom) {
            DB::table('wise_knowledge_items')
                ->whereNull('wise_api_key_id')
                ->where('status', 'published')
                ->where('meta->seeded_from', $seededFrom)
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
