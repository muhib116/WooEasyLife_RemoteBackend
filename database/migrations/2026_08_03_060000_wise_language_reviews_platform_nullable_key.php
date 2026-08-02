<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Platform Train may open Language Discovery reviews with no merchant key.
 * Unique token per scope via functional IFNULL(key, 0) index (MySQL 8+).
 *
 * Idempotent: safe if an earlier partial run already made the column nullable.
 */
return new class extends Migration
{
    public function up(): void
    {
        $nullability = DB::selectOne(
            "SHOW COLUMNS FROM wise_language_reviews WHERE Field = 'wise_api_key_id'"
        );
        $alreadyNullable = strtoupper((string) ($nullability->Null ?? '')) === 'YES';

        if (! $alreadyNullable) {
            Schema::table('wise_language_reviews', function (Blueprint $table) {
                $table->dropForeign(['wise_api_key_id']);
            });

            $this->dropIndexIfExists('wise_lang_review_token_unique');

            DB::statement('ALTER TABLE wise_language_reviews MODIFY wise_api_key_id BIGINT UNSIGNED NULL');

            Schema::table('wise_language_reviews', function (Blueprint $table) {
                $table->foreign('wise_api_key_id')
                    ->references('id')
                    ->on('wise_api_keys')
                    ->nullOnDelete();
            });
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
        $this->dropIndexIfExists('wise_lang_review_token_unique');

        Schema::table('wise_language_reviews', function (Blueprint $table) {
            $table->dropForeign(['wise_api_key_id']);
        });

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

    private function ensureNullOnDeleteForeign(): void
    {
        $rule = DB::selectOne(
            "SELECT DELETE_RULE FROM information_schema.REFERENTIAL_CONSTRAINTS
             WHERE CONSTRAINT_SCHEMA = DATABASE()
               AND TABLE_NAME = 'wise_language_reviews'
               AND CONSTRAINT_NAME = 'wise_language_reviews_wise_api_key_id_foreign'
             LIMIT 1"
        );
        if ($rule && strtoupper((string) $rule->DELETE_RULE) === 'SET NULL') {
            return;
        }

        Schema::table('wise_language_reviews', function (Blueprint $table) {
            $table->dropForeign(['wise_api_key_id']);
        });
        Schema::table('wise_language_reviews', function (Blueprint $table) {
            $table->foreign('wise_api_key_id')
                ->references('id')
                ->on('wise_api_keys')
                ->nullOnDelete();
        });
    }
};
