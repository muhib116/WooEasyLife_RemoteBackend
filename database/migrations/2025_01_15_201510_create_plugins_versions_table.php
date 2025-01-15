<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('plugins_versions', function (Blueprint $table) {
            $table->id();
            $table->string('version')->nullable();
            $table->text('path')->nullable();
            $table->bigInteger('download_count')->default(0);
            $table->unsignedBigInteger('created_by')->nullable();
            $table->json('settings')->nullable();
            $table->softDeletes();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('plugins_versions');
    }
};
