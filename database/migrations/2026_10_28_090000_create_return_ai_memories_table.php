<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('return_ai_memories', function (Blueprint $table) {
            $table->id();
            // Store scope: the license's website, or the token when it has none.
            $table->unsignedBigInteger('website_id')->nullable();
            $table->unsignedBigInteger('personal_access_token_id')->nullable();
            $table->unsignedBigInteger('user_id')->nullable()->index();
            $table->string('situation', 200)->nullable();
            $table->string('instruction', 400);
            $table->boolean('active')->default(true);
            $table->timestamps();

            $table->index(['website_id', 'active']);
            $table->index(['personal_access_token_id', 'active']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('return_ai_memories');
    }
};
