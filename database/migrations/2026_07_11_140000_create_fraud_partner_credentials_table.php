<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('fraud_partner_credentials', function (Blueprint $table) {
            $table->id();
            $table->string('courier', 32);
            $table->string('label')->nullable();
            $table->string('identifier');
            $table->text('secret');
            $table->boolean('is_active')->default(true);
            $table->unsignedInteger('priority')->default(100);
            $table->timestamp('last_used_at')->nullable();
            $table->timestamp('last_success_at')->nullable();
            $table->string('last_error', 500)->nullable();
            $table->timestamps();

            $table->unique(['courier', 'identifier']);
            $table->index(['courier', 'is_active', 'priority']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('fraud_partner_credentials');
    }
};
