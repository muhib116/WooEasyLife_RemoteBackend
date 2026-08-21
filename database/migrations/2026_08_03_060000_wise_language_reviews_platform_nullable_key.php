<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Platform Train may open Language Discovery reviews with no merchant key.
 * Unique token per scope via functional IFNULL(key, 0) index (MySQL 8+).
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

            if (! $this->foreignKeyExists('wise_language_reviews_wise_api_key_id_foreign')) {
                Schema::table('wise_language_reviews', function (Blueprint $table) {
                    $table->foreign('wise_api_key_id')
                        ->references('id')
                        ->on('wise_api_keys')
                        ->nullOnDelete();
                });
            }
        } else {
            // Partial prior run: unique may already be gone; FK should be nullOnDelete.
            $this->dropIndexIfExists('wise_lang_review_token_unique');
            $this->ensureNullOnDeleteForeign();
        }

        if (! $this->indexExists('wise_lang_review_token_unique')) {
            DB::statement(
                'CREATE UNIQUE INDEX wise_lang_review_token_unique
                 ON wise_language_reviews ((IFNULL(wise_api_key_id, 0)), token)'
            );
        }
    }

    public function down(): void
    {
        if (! Schema::hasTable('wise_language_reviews')) {
            return;
        }

        $this->dropIndexIfExists('wise_lang_review_token_unique');
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

    private function foreignKeyExists(string $constraintName): bool
    {
        $row = DB::selectOne(
            'SELECT 1 AS ok FROM information_schema.REFERENTIAL_CONSTRAINTS
             WHERE CONSTRAINT_SCHEMA = DATABASE()
               AND TABLE_NAME = ?
               AND CONSTRAINT_NAME = ?
             LIMIT 1',
            ['wise_language_reviews', $constraintName]
        );

        return $row !== null;
    }

    /**
     * Drop any FK on the given column, regardless of constraint name.
     */
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
            "SELECT CONSTRAINT_NAME, DELETE_RULE
             FROM information_schema.REFERENTIAL_CONSTRAINTS
             WHERE CONSTRAINT_SCHEMA = DATABASE()
               AND TABLE_NAME = 'wise_language_reviews'
               AND CONSTRAINT_NAME IN (
                   SELECT DISTINCT CONSTRAINT_NAME
                   FROM information_schema.KEY_COLUMN_USAGE
                   WHERE TABLE_SCHEMA = DATABASE()
                     AND TABLE_NAME = 'wise_language_reviews'
                     AND COLUMN_NAME = 'wise_api_key_id'
                     AND REFERENCED_TABLE_NAME IS NOT NULL
               )
             LIMIT 1"
        );

        if ($rule && strtoupper((string) $rule->DELETE_RULE) === 'SET NULL') {
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
