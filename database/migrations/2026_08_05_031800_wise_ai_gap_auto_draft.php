<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('wise_turns', function (Blueprint $table) {
            $table->unsignedBigInteger('gap_auto_draft_id')->nullable()->after('gap_knowledge_id');
            $table->index('gap_auto_draft_id', 'wise_turns_gap_auto_draft_idx');
        });
    }

    public function down(): void
    {
        Schema::table('wise_turns', function (Blueprint $table) {
            $table->dropIndex('wise_turns_gap_auto_draft_idx');
            $table->dropColumn('gap_auto_draft_id');
        });
    }
};
