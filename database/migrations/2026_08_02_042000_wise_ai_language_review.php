<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('wise_language_entries')) {
            Schema::create('wise_language_entries', function (Blueprint $table) {
                $table->id();
                $table->foreignId('wise_api_key_id')->nullable()->constrained('wise_api_keys')->nullOnDelete();
                $table->string('type', 40); // abbrev|sms|banglish|phonetic|commerce|filler
                $table->string('from_text', 120);
                $table->string('to_text', 191)->nullable(); // null/empty for filler strip
                $table->string('status', 20)->default('draft'); // draft|published
                $table->boolean('enabled')->default(true);
                $table->unsignedInteger('version')->default(1);
                $table->json('meta')->nullable();
                $table->timestamps();

                $table->unique(['wise_api_key_id', 'type', 'from_text'], 'wise_lang_entry_unique');
                $table->index(['status', 'enabled']);
            });
        }

        if (! Schema::hasTable('wise_language_reviews')) {
            Schema::create('wise_language_reviews', function (Blueprint $table) {
                $table->id();
                $table->foreignId('wise_api_key_id')->constrained('wise_api_keys')->cascadeOnDelete();
                $table->string('token', 120);
                $table->string('sample_text', 500)->nullable();
                $table->unsignedInteger('hit_count')->default(1);
                $table->string('status', 20)->default('open'); // open|ignored|promoted
                $table->foreignId('wise_language_entry_id')->nullable()->constrained('wise_language_entries')->nullOnDelete();
                $table->unsignedBigInteger('first_turn_id')->nullable();
                $table->unsignedBigInteger('last_turn_id')->nullable();
                $table->timestamp('last_seen_at')->nullable();
                $table->timestamp('handled_at')->nullable();
                $table->timestamps();

                $table->unique(['wise_api_key_id', 'token'], 'wise_lang_review_token_unique');
                $table->index(['status', 'hit_count']);
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('wise_language_reviews');
        Schema::dropIfExists('wise_language_entries');
    }
};
