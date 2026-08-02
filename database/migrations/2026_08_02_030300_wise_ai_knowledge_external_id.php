<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('wise_knowledge_items', function (Blueprint $table) {
            $table->string('external_id', 64)->nullable()->after('wise_api_key_id');
            $table->json('meta')->nullable()->after('keywords');
            $table->index(['wise_api_key_id', 'external_id'], 'wise_knowledge_external_idx');
        });
    }

    public function down(): void
    {
        Schema::table('wise_knowledge_items', function (Blueprint $table) {
            $table->dropIndex('wise_knowledge_external_idx');
            $table->dropColumn(['external_id', 'meta']);
        });
    }
};
