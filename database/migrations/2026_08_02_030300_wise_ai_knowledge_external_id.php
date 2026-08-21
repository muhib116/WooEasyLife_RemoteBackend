<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('wise_knowledge_items')) {
            return;
        }

        Schema::table('wise_knowledge_items', function (Blueprint $table) {
            if (! Schema::hasColumn('wise_knowledge_items', 'external_id')) {
                $table->string('external_id', 64)->nullable()->after('wise_api_key_id');
            }
            if (! Schema::hasColumn('wise_knowledge_items', 'meta')) {
                $table->json('meta')->nullable()->after('keywords');
            }
        });

        $indexes = collect(Schema::getConnection()->select('SHOW INDEX FROM wise_knowledge_items'))
            ->pluck('Key_name')
            ->unique()
            ->all();

        if (
            Schema::hasColumn('wise_knowledge_items', 'external_id')
            && ! in_array('wise_knowledge_external_idx', $indexes, true)
        ) {
            Schema::table('wise_knowledge_items', function (Blueprint $table) {
                $table->index(['wise_api_key_id', 'external_id'], 'wise_knowledge_external_idx');
            });
        }
    }

    public function down(): void
    {
        if (! Schema::hasTable('wise_knowledge_items')) {
            return;
        }

        Schema::table('wise_knowledge_items', function (Blueprint $table) {
            $indexes = collect(Schema::getConnection()->select('SHOW INDEX FROM wise_knowledge_items'))
                ->pluck('Key_name')
                ->unique()
                ->all();

            if (in_array('wise_knowledge_external_idx', $indexes, true)) {
                $table->dropIndex('wise_knowledge_external_idx');
            }

            $drop = [];
            if (Schema::hasColumn('wise_knowledge_items', 'external_id')) {
                $drop[] = 'external_id';
            }
            if (Schema::hasColumn('wise_knowledge_items', 'meta')) {
                $drop[] = 'meta';
            }
            if ($drop !== []) {
                $table->dropColumn($drop);
            }
        });
    }
};
