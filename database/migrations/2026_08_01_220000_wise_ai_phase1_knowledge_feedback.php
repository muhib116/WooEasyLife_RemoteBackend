<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('wise_turns', function (Blueprint $table) {
            $table->json('config_snapshot')->nullable()->after('payload');
            $table->json('evidence')->nullable()->after('decision');
            $table->json('trace')->nullable()->after('evidence');
            $table->boolean('gap')->default(false)->after('status');
        });

        Schema::create('wise_knowledge_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('wise_api_key_id')->index();
            $table->string('type', 40)->default('faq')->index();
            $table->string('title');
            $table->text('question')->nullable();
            $table->text('answer');
            $table->json('keywords')->nullable();
            $table->string('status', 20)->default('draft')->index();
            $table->unsignedInteger('version')->default(1);
            $table->timestamps();

            $table->index(['wise_api_key_id', 'status']);
        });

        Schema::create('wise_feedback', function (Blueprint $table) {
            $table->id();
            $table->foreignId('wise_api_key_id')->index();
            $table->foreignId('wise_turn_id')->index();
            $table->string('outcome', 20)->index();
            $table->string('reason_code', 60)->nullable();
            $table->text('edited_reply')->nullable();
            $table->json('meta')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('wise_feedback');
        Schema::dropIfExists('wise_knowledge_items');

        Schema::table('wise_turns', function (Blueprint $table) {
            $table->dropColumn(['config_snapshot', 'evidence', 'trace', 'gap']);
        });
    }
};
