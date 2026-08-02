<?php

use App\WiseAi\Language\BclcBootstrap;
use App\WiseAi\Language\PackCompiler;
use App\Models\WiseAi\WiseLanguagePack;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\Schema;

/**
 * Ensure Core/Commerce/Messenger packs exist after BCLC schema.
 * Recompile when packs already seeded (content-hash formula 1.0.1).
 */
return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('wise_language_packs')) {
            return;
        }

        if (WiseLanguagePack::query()->count() === 0) {
            app(BclcBootstrap::class)->run();

            return;
        }

        $compiler = app(PackCompiler::class);
        foreach (WiseLanguagePack::query()->orderBy('id')->get() as $pack) {
            $compiler->compileAndPublish($pack);
        }
    }

    public function down(): void
    {
        // Keep seeded corpus — teardown is drop of L0 tables.
    }
};
