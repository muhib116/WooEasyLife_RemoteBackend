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
        Schema::table('wise_knowledge_items', function (Blueprint $table) {
            $table->mediumText('match_text')->nullable()->after('keywords');
            $table->index(['wise_api_key_id', 'status', 'type'], 'wise_knowledge_key_status_type');
            $table->index(['status', 'scope'], 'wise_knowledge_status_scope');
            $table->index(['external_id', 'status', 'type'], 'wise_knowledge_ext_status_type');
        });

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
        Schema::table('wise_knowledge_items', function (Blueprint $table) {
            $table->dropIndex('wise_knowledge_key_status_type');
            $table->dropIndex('wise_knowledge_status_scope');
            $table->dropIndex('wise_knowledge_ext_status_type');
            $table->dropColumn('match_text');
        });
    }
};
