<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('messenger_page_connections', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('access_token_id')->index();
            $table->unsignedBigInteger('user_id')->nullable()->index();
            $table->unsignedBigInteger('website_id')->nullable()->index();
            $table->string('page_id', 64);
            $table->string('page_name')->nullable();
            $table->text('page_picture')->nullable();
            $table->text('page_access_token');
            $table->text('user_access_token')->nullable();
            $table->json('scopes')->nullable();
            $table->string('status', 32)->default('connected')->index();
            $table->string('site_url')->nullable();
            $table->string('return_url', 1024)->nullable();
            $table->timestamp('webhook_subscribed_at')->nullable();
            $table->timestamp('connected_at')->nullable();
            $table->timestamp('disconnected_at')->nullable();
            $table->timestamps();

            $table->unique(['access_token_id', 'page_id'], 'messenger_page_token_page_unique');
            $table->index(['page_id', 'status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('messenger_page_connections');
    }
};
