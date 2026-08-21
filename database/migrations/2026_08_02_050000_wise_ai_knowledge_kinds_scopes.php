<?php

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

        if (! Schema::hasColumn('wise_knowledge_items', 'scope')) {
            Schema::table('wise_knowledge_items', function (Blueprint $table) {
                $table->string('scope', 20)->default('merchant')->after('type')->index();
            });
        }

        // Platform-scoped packs may omit a merchant key (marketplace foundation).
        $nullability = DB::selectOne(
            "SHOW COLUMNS FROM wise_knowledge_items WHERE Field = 'wise_api_key_id'"
        );
        if (strtoupper((string) ($nullability->Null ?? '')) !== 'YES') {
            DB::statement('ALTER TABLE wise_knowledge_items MODIFY wise_api_key_id BIGINT UNSIGNED NULL');
        }

        // Legacy "other" → fact (kind taxonomy). Validation still accepts "other" as alias.
        DB::table('wise_knowledge_items')
            ->where('type', 'other')
            ->update(['type' => 'fact']);
    }

    public function down(): void
    {
        if (! Schema::hasTable('wise_knowledge_items')) {
            return;
        }

        DB::table('wise_knowledge_items')
            ->where('type', 'fact')
            ->update(['type' => 'other']);

        DB::table('wise_knowledge_items')
            ->whereNull('wise_api_key_id')
            ->update(['wise_api_key_id' => 0]);

        DB::statement('ALTER TABLE wise_knowledge_items MODIFY wise_api_key_id BIGINT UNSIGNED NOT NULL');

        if (Schema::hasColumn('wise_knowledge_items', 'scope')) {
            Schema::table('wise_knowledge_items', function (Blueprint $table) {
                $table->dropColumn('scope');
            });
        }
    }
};
