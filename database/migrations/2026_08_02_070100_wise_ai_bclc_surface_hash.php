<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Hotfix for DBs that ran BCLC L0 before surface_hash existed.
 * Fresh installs already get surface_hash from 070000.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('wise_language_surfaces')) {
            return;
        }

        if (! Schema::hasColumn('wise_language_surfaces', 'surface_hash')) {
            Schema::table('wise_language_surfaces', function (Blueprint $table) {
                $table->string('surface_hash', 40)->nullable()->after('surface_text');
            });
        }

        $rows = DB::table('wise_language_surfaces')
            ->where(function ($q) {
                $q->whereNull('surface_hash')->orWhere('surface_hash', '');
            })
            ->select('id', 'surface_text')
            ->get();
        foreach ($rows as $row) {
            DB::table('wise_language_surfaces')->where('id', $row->id)->update([
                'surface_hash' => hash('sha1', (string) $row->surface_text),
            ]);
        }

        // Ensure pack_id has a dedicated index so we can replace the composite unique.
        $hasPackIdx = collect(DB::select('SHOW INDEX FROM wise_language_surfaces'))
            ->contains(fn ($i) => $i->Key_name === 'wise_lang_surface_pack_idx');
        if (! $hasPackIdx) {
            DB::statement('ALTER TABLE wise_language_surfaces ADD INDEX wise_lang_surface_pack_idx (pack_id)');
        }

        $uniqueCols = collect(DB::select('SHOW INDEX FROM wise_language_surfaces'))
            ->where('Key_name', 'wise_lang_surface_unique')
            ->pluck('Column_name')
            ->values()
            ->all();

        if ($uniqueCols === ['pack_id', 'surface_text']) {
            DB::statement('ALTER TABLE wise_language_surfaces DROP INDEX wise_lang_surface_unique');
            DB::statement('ALTER TABLE wise_language_surfaces ADD UNIQUE wise_lang_surface_unique (pack_id, surface_hash)');
        } elseif ($uniqueCols === []) {
            DB::statement('ALTER TABLE wise_language_surfaces ADD UNIQUE wise_lang_surface_unique (pack_id, surface_hash)');
        }
    }

    public function down(): void
    {
        // No-op — keep hash column for protocol safety.
    }
};
