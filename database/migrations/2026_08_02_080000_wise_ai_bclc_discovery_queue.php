<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * BCLC L2 — evolve Language Review into a ranked Discovery Queue.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('wise_language_reviews')) {
            return;
        }

        Schema::table('wise_language_reviews', function (Blueprint $table) {
            if (! Schema::hasColumn('wise_language_reviews', 'kind')) {
                $table->string('kind', 40)->default('token')->after('token'); // token|phrase|abbrev|banglish|emoji|negotiation|regional
            }
            if (! Schema::hasColumn('wise_language_reviews', 'channel')) {
                $table->string('channel', 60)->nullable()->after('kind');
            }
            if (! Schema::hasColumn('wise_language_reviews', 'suggested_pack_slug')) {
                $table->string('suggested_pack_slug', 80)->nullable()->after('hit_count');
            }
            if (! Schema::hasColumn('wise_language_reviews', 'suggested_category')) {
                $table->string('suggested_category', 40)->nullable()->after('suggested_pack_slug');
            }
            if (! Schema::hasColumn('wise_language_reviews', 'suggested_concept_key')) {
                $table->string('suggested_concept_key', 120)->nullable()->after('suggested_category');
            }
            if (! Schema::hasColumn('wise_language_reviews', 'rank_score')) {
                $table->decimal('rank_score', 10, 2)->default(0)->after('suggested_concept_key');
            }
            if (! Schema::hasColumn('wise_language_reviews', 'key_breadth')) {
                $table->unsignedInteger('key_breadth')->default(1)->after('rank_score');
            }
        });

        // Indexes added idempotently in 080100 (avoid duplicate-index failures on retry).
    }

    public function down(): void
    {
        if (! Schema::hasTable('wise_language_reviews')) {
            return;
        }

        Schema::table('wise_language_reviews', function (Blueprint $table) {
            try {
                $table->dropIndex('wise_lang_review_rank_idx');
            } catch (\Throwable) {
            }
            try {
                $table->dropIndex('wise_lang_review_kind_idx');
            } catch (\Throwable) {
            }
            foreach ([
                'kind', 'channel', 'suggested_pack_slug', 'suggested_category',
                'suggested_concept_key', 'rank_score', 'key_breadth',
            ] as $col) {
                if (Schema::hasColumn('wise_language_reviews', $col)) {
                    $table->dropColumn($col);
                }
            }
        });
    }
};
