<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('meta_pages', function (Blueprint $table) {
            $table->id();
            $table->foreignId('meta_connection_id')
                ->constrained('meta_connections')
                ->cascadeOnDelete();
            $table->string('page_id', 64);
            $table->string('page_name', 191)->nullable();
            // Same modeling as messenger_page_connections.page_access_token (encrypted cast).
            $table->text('page_access_token')->nullable();
            $table->boolean('is_tracked')->default(false);
            $table->boolean('webhook_subscribed')->default(false);
            $table->timestamp('created_at')->useCurrent();

            $table->unique(['meta_connection_id', 'page_id'], 'meta_pages_connection_page_unique');
            $table->index('page_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('meta_pages');
    }
};
