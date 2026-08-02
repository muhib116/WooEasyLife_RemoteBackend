<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasColumn('wise_knowledge_items', 'external_id')) {
            return;
        }

        $driver = Schema::getConnection()->getDriverName();
        if ($driver === 'mysql') {
            DB::statement('ALTER TABLE wise_knowledge_items MODIFY external_id VARCHAR(191) NULL');
        } elseif ($driver === 'pgsql') {
            DB::statement('ALTER TABLE wise_knowledge_items ALTER COLUMN external_id TYPE VARCHAR(191)');
        }
    }

    public function down(): void
    {
        if (! Schema::hasColumn('wise_knowledge_items', 'external_id')) {
            return;
        }

        $driver = Schema::getConnection()->getDriverName();
        if ($driver === 'mysql') {
            DB::statement('ALTER TABLE wise_knowledge_items MODIFY external_id VARCHAR(64) NULL');
        } elseif ($driver === 'pgsql') {
            DB::statement('ALTER TABLE wise_knowledge_items ALTER COLUMN external_id TYPE VARCHAR(64)');
        }
    }
};
