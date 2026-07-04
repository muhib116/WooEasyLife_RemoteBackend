<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('customer_notices', function (Blueprint $table) {
            $table->id();
            $table->string('title');
            $table->text('body');
            $table->string('type', 32)->default('general');
            $table->string('severity', 16)->default('info');
            $table->string('audience', 32)->default('all');
            $table->string('cta_label')->nullable();
            $table->string('cta_url', 512)->nullable();
            $table->boolean('is_dismissible')->default(true);
            $table->boolean('is_active')->default(true);
            $table->integer('priority')->default(0);
            $table->timestamp('starts_at')->nullable();
            $table->timestamp('ends_at')->nullable();
            $table->timestamps();

            $table->index(['is_active', 'audience']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('customer_notices');
    }
};
