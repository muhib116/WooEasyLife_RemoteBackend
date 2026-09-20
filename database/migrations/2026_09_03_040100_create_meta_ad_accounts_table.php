<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('meta_ad_accounts', function (Blueprint $table) {
            $table->id();
            $table->foreignId('meta_connection_id')
                ->constrained('meta_connections')
                ->cascadeOnDelete();
            $table->string('account_id', 64); // act_XXXXXXXXX
            $table->string('account_name', 191)->nullable();
            $table->string('currency', 8)->nullable();
            $table->boolean('is_tracked')->default(false);
            $table->timestamp('created_at')->useCurrent();

            $table->unique(['meta_connection_id', 'account_id'], 'meta_ad_accounts_connection_account_unique');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('meta_ad_accounts');
    }
};
