<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Hot-path indexes for plugin API lookups (live-safe additive only).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('user_packages', function (Blueprint $table) {
            if (! $this->indexExists('user_packages', 'user_packages_user_id_is_active_index')) {
                $table->index(['user_id', 'is_active'], 'user_packages_user_id_is_active_index');
            }
        });

        Schema::table('sms_balances', function (Blueprint $table) {
            if (! $this->indexExists('sms_balances', 'sms_balances_user_id_index')) {
                $table->index('user_id', 'sms_balances_user_id_index');
            }
        });

        Schema::table('sms_recharges', function (Blueprint $table) {
            if (! $this->indexExists('sms_recharges', 'sms_recharges_user_id_status_index')) {
                $table->index(['user_id', 'status'], 'sms_recharges_user_id_status_index');
            }
        });

        // Prefix lengths keep the composite under MySQL utf8mb4 index limits.
        if (! $this->indexExists('route_hits', 'route_hits_upsert_lookup_index')) {
            DB::statement(
                'ALTER TABLE route_hits ADD INDEX route_hits_upsert_lookup_index (path(120), domain(120), status(32), created_at)'
            );
        }
    }

    public function down(): void
    {
        Schema::table('user_packages', function (Blueprint $table) {
            if ($this->indexExists('user_packages', 'user_packages_user_id_is_active_index')) {
                $table->dropIndex('user_packages_user_id_is_active_index');
            }
        });

        Schema::table('sms_balances', function (Blueprint $table) {
            if ($this->indexExists('sms_balances', 'sms_balances_user_id_index')) {
                $table->dropIndex('sms_balances_user_id_index');
            }
        });

        Schema::table('sms_recharges', function (Blueprint $table) {
            if ($this->indexExists('sms_recharges', 'sms_recharges_user_id_status_index')) {
                $table->dropIndex('sms_recharges_user_id_status_index');
            }
        });

        if ($this->indexExists('route_hits', 'route_hits_upsert_lookup_index')) {
            DB::statement('ALTER TABLE route_hits DROP INDEX route_hits_upsert_lookup_index');
        }
    }

    private function indexExists(string $table, string $indexName): bool
    {
        try {
            $database = Schema::getConnection()->getDatabaseName();
            $rows = Schema::getConnection()->select(
                'select 1 from information_schema.statistics where table_schema = ? and table_name = ? and index_name = ? limit 1',
                [$database, $table, $indexName]
            );

            return $rows !== [];
        } catch (\Throwable) {
            return false;
        }
    }
};
