<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Platform Train may open Language Discovery reviews with no merchant key.
 * Unique token per scope via plain wise_api_key_scope (= IFNULL(key, 0)) column.
 * Avoids MySQL-8-only functional indexes and FK+generated-column conflicts
 * (MariaDB / MySQL safe). App model keeps scope in sync on save.
 *
 * Idempotent: safe if FK/index/nullability already match the target schema.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('wise_language_reviews')) {
            return;
        }

        $nullability = DB::selectOne(
            "SHOW COLUMNS FROM wise_language_reviews WHERE Field = 'wise_api_key_id'"
        );
        if ($nullability === null) {
            return;
        }

        $alreadyNullable = strtoupper((string) ($nullability->Null ?? '')) === 'YES';

        if (! $alreadyNullable) {
            $this->dropForeignOnColumnIfExists('wise_api_key_id');
            $this->dropIndexIfExists('wise_lang_review_token_unique');

            DB::statement('ALTER TABLE wise_language_reviews MODIFY wise_api_key_id BIGINT UNSIGNED NULL');

            if (! $this->foreignKeyOnColumnExists('wise_api_key_id')) {
                Schema::table('wise_language_reviews', function (Blueprint $table) {
                    $table->foreign('wise_api_key_id')
                        ->references('id')
                        ->on('wise_api_keys')
                        ->nullOnDelete();
                });
            }
        } else {
            $this->ensureNullOnDeleteForeign();
        }

        $this->ensureScopeColumnAndUnique();
    }

    public function down(): void
    {
        if (! Schema::hasTable('wise_language_reviews')) {
            return;
        }

        $this->dropIndexIfExists('wise_lang_review_token_unique');

        if (Schema::hasColumn('wise_language_reviews', 'wise_api_key_scope')) {
            Schema::table('wise_language_reviews', function (Blueprint $table) {
                $table->dropColumn('wise_api_key_scope');
            });
        }

        $this->dropForeignOnColumnIfExists('wise_api_key_id');

        DB::table('wise_language_reviews')->whereNull('wise_api_key_id')->delete();

        DB::statement('ALTER TABLE wise_language_reviews MODIFY wise_api_key_id BIGINT UNSIGNED NOT NULL');

        Schema::table('wise_language_reviews', function (Blueprint $table) {
            $table->foreign('wise_api_key_id')
                ->references('id')
                ->on('wise_api_keys')
                ->cascadeOnDelete();
            $table->unique(['wise_api_key_id', 'token'], 'wise_lang_review_token_unique');
        });
    }

    private function ensureScopeColumnAndUnique(): void
    {
        if (! Schema::hasColumn('wise_language_reviews', 'wise_api_key_scope')) {
            Schema::table('wise_language_reviews', function (Blueprint $table) {
                $table->unsignedBigInteger('wise_api_key_scope')->default(0);
            });
        }

        DB::statement(
            'UPDATE wise_language_reviews SET wise_api_key_scope = IFNULL(wise_api_key_id, 0)'
        );

        if ($this->indexExists('wise_lang_review_token_unique') && ! $this->uniqueIsOnScopeAndToken()) {
            $this->dropIndexIfExists('wise_lang_review_token_unique');
        }

        if (! $this->indexExists('wise_lang_review_token_unique')) {
            Schema::table('wise_language_reviews', function (Blueprint $table) {
                $table->unique(['wise_api_key_scope', 'token'], 'wise_lang_review_token_unique');
            });
        }
    }

    private function uniqueIsOnScopeAndToken(): bool
    {
        $cols = DB::select(
            'SELECT COLUMN_NAME AS name
             FROM information_schema.STATISTICS
             WHERE TABLE_SCHEMA = DATABASE()
               AND TABLE_NAME = ?
               AND INDEX_NAME = ?
             ORDER BY SEQ_IN_INDEX',
            ['wise_language_reviews', 'wise_lang_review_token_unique']
        );

        if (count($cols) !== 2) {
            return false;
        }

        return ($cols[0]->name ?? '') === 'wise_api_key_scope'
            && ($cols[1]->name ?? '') === 'token';
    }

    private function dropIndexIfExists(string $name): void
    {
        if ($this->indexExists($name)) {
            DB::statement("ALTER TABLE wise_language_reviews DROP INDEX `{$name}`");
        }
    }

    private function indexExists(string $name): bool
    {
        $row = DB::selectOne(
            'SELECT 1 AS ok FROM information_schema.STATISTICS
             WHERE TABLE_SCHEMA = DATABASE()
               AND TABLE_NAME = ?
               AND INDEX_NAME = ?
             LIMIT 1',
            ['wise_language_reviews', $name]
        );

        return $row !== null;
    }

    private function foreignKeyOnColumnExists(string $column): bool
    {
        $row = DB::selectOne(
            'SELECT 1 AS ok FROM information_schema.KEY_COLUMN_USAGE
             WHERE TABLE_SCHEMA = DATABASE()
               AND TABLE_NAME = ?
               AND COLUMN_NAME = ?
               AND REFERENCED_TABLE_NAME IS NOT NULL
             LIMIT 1',
            ['wise_language_reviews', $column]
        );

        return $row !== null;
    }

    private function dropForeignOnColumnIfExists(string $column): void
    {
        $constraints = DB::select(
            'SELECT DISTINCT CONSTRAINT_NAME AS name
             FROM information_schema.KEY_COLUMN_USAGE
             WHERE TABLE_SCHEMA = DATABASE()
               AND TABLE_NAME = ?
               AND COLUMN_NAME = ?
               AND REFERENCED_TABLE_NAME IS NOT NULL',
            ['wise_language_reviews', $column]
        );

        foreach ($constraints as $constraint) {
            $name = (string) ($constraint->name ?? '');
            if ($name === '') {
                continue;
            }
            DB::statement("ALTER TABLE wise_language_reviews DROP FOREIGN KEY `{$name}`");
        }
    }

    private function ensureNullOnDeleteForeign(): void
    {
        $rule = DB::selectOne(
            "SELECT rc.DELETE_RULE AS delete_rule
             FROM information_schema.REFERENTIAL_CONSTRAINTS rc
             INNER JOIN information_schema.KEY_COLUMN_USAGE kcu
               ON kcu.CONSTRAINT_SCHEMA = rc.CONSTRAINT_SCHEMA
              AND kcu.CONSTRAINT_NAME = rc.CONSTRAINT_NAME
              AND kcu.TABLE_NAME = rc.TABLE_NAME
             WHERE rc.CONSTRAINT_SCHEMA = DATABASE()
               AND rc.TABLE_NAME = 'wise_language_reviews'
               AND kcu.COLUMN_NAME = 'wise_api_key_id'
               AND kcu.REFERENCED_TABLE_NAME IS NOT NULL
             LIMIT 1"
        );

        if ($rule && strtoupper((string) $rule->delete_rule) === 'SET NULL') {
            return;
        }

        $this->dropForeignOnColumnIfExists('wise_api_key_id');

        Schema::table('wise_language_reviews', function (Blueprint $table) {
            $table->foreign('wise_api_key_id')
                ->references('id')
                ->on('wise_api_keys')
                ->nullOnDelete();
        });
    }
};
