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
        Schema::create('msg_images', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('ws_message_id')->nullable();
            $table->text('ref_path')->nullable();
            $table->text('local_path')->nullable();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('msg_images');
    }
};
