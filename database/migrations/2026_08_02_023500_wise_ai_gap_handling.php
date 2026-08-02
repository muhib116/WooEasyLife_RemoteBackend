<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('wise_turns', function (Blueprint $table) {
            $table->timestamp('gap_handled_at')->nullable()->after('gap');
            $table->unsignedBigInteger('gap_knowledge_id')->nullable()->after('gap_handled_at');
            $table->index(['gap', 'gap_handled_at'], 'wise_turns_gap_open_idx');
        });
    }

    public function down(): void
    {
        Schema::table('wise_turns', function (Blueprint $table) {
            $table->dropIndex('wise_turns_gap_open_idx');
            $table->dropColumn(['gap_handled_at', 'gap_knowledge_id']);
        });
    }
};
