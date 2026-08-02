<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * BCLC L0 — Bangladesh Commerce Language Intelligence authoring + compile artifacts.
 * Runtime decide path loads published artifacts only (never row-scans surfaces).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('wise_language_packs', function (Blueprint $table) {
            $table->id();
            $table->string('slug', 80)->unique();
            $table->string('kind', 40); // core|channel|commerce|regional|industry|merchant|other
            $table->string('name', 120);
            $table->string('semver', 40)->default('1.0.0');
            $table->string('status', 20)->default('draft'); // draft|published|deprecated
            $table->string('locale_scope', 40)->default('bd');
            $table->json('depends_on')->nullable();
            $table->unsignedInteger('compiler_min_version')->default(1);
            $table->json('meta')->nullable();
            $table->timestamps();

            $table->index(['status', 'kind']);
        });

        Schema::create('wise_language_concepts', function (Blueprint $table) {
            $table->id();
            $table->foreignId('pack_id')->constrained('wise_language_packs')->cascadeOnDelete();
            $table->string('category', 40); // abbrev|sms|banglish|phonetic|commerce|filler|emoji|…
            $table->string('concept_key', 120);
            $table->string('gloss_en', 255)->nullable();
            $table->string('gloss_bn', 255)->nullable();
            $table->string('status', 20)->default('published');
            $table->json('meta')->nullable();
            $table->timestamps();

            $table->unique(['pack_id', 'concept_key'], 'wise_lang_concept_unique');
            $table->index(['pack_id', 'category'], 'wise_lang_concept_cat_idx');
        });

        Schema::create('wise_language_surfaces', function (Blueprint $table) {
            $table->id();
            $table->foreignId('pack_id')->constrained('wise_language_packs')->cascadeOnDelete();
            $table->foreignId('concept_id')->constrained('wise_language_concepts')->cascadeOnDelete();
            $table->string('surface_text', 191);
            // Binary-safe uniqueness (emoji variants collide under unicode_ci).
            $table->string('surface_hash', 40);
            $table->string('to_text', 255)->nullable(); // expansion / strip marker
            $table->string('script', 20)->nullable(); // latin|bengali|emoji|mixed
            $table->json('region_tags')->nullable();
            $table->decimal('confidence', 5, 2)->nullable();
            $table->unsignedInteger('popularity')->default(0);
            $table->unsignedInteger('frequency')->default(0);
            $table->timestamp('last_seen_at')->nullable();
            $table->unsignedInteger('merchant_count')->default(0);
            $table->unsignedInteger('region_count')->default(0);
            $table->unsignedInteger('industry_count')->default(0);
            $table->string('approval_status', 20)->default('published'); // draft|published|rejected
            $table->boolean('deprecated')->default(false);
            $table->unsignedBigInteger('replacement_concept_id')->nullable();
            $table->string('evidence_source', 40)->default('seed'); // seed|review|import|merchant
            $table->json('meta')->nullable();
            $table->timestamps();

            $table->unique(['pack_id', 'surface_hash'], 'wise_lang_surface_unique');
            $table->index('pack_id', 'wise_lang_surface_pack_idx');
            $table->index(['approval_status', 'deprecated'], 'wise_lang_surface_pub_idx');
            $table->index(['concept_id'], 'wise_lang_surface_concept_idx');
        });

        Schema::create('wise_language_pack_assignments', function (Blueprint $table) {
            $table->id();
            $table->string('target_type', 40); // platform|industry|wise_api_key|channel|region
            $table->string('target_id', 80)->nullable();
            $table->foreignId('pack_id')->constrained('wise_language_packs')->cascadeOnDelete();
            $table->unsignedSmallInteger('priority')->default(100); // higher = more specific overlay
            $table->boolean('enabled')->default(true);
            $table->json('meta')->nullable();
            $table->timestamps();

            $table->unique(['target_type', 'target_id', 'pack_id'], 'wise_lang_assign_unique');
            $table->index(['target_type', 'target_id', 'enabled'], 'wise_lang_assign_target_idx');
        });

        Schema::create('wise_language_artifacts', function (Blueprint $table) {
            $table->id();
            $table->foreignId('pack_id')->constrained('wise_language_packs')->cascadeOnDelete();
            $table->string('pack_version', 40);
            $table->string('compiler_version', 40);
            $table->string('content_hash', 64);
            $table->longText('artifact_json');
            $table->string('status', 20)->default('published'); // draft|published|superseded
            $table->timestamp('published_at')->nullable();
            $table->timestamps();

            $table->unique(['pack_id', 'content_hash'], 'wise_lang_artifact_hash_uq');
            $table->index(['pack_id', 'status', 'published_at'], 'wise_lang_artifact_pub_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('wise_language_artifacts');
        Schema::dropIfExists('wise_language_pack_assignments');
        Schema::dropIfExists('wise_language_surfaces');
        Schema::dropIfExists('wise_language_concepts');
        Schema::dropIfExists('wise_language_packs');
    }
};
