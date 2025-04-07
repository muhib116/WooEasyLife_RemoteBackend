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
        Schema::create('route_hits', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('user_id')->nullable();
            $table->string('group')->nullable();
            $table->string('path')->nullable();
            $table->string('domain')->nullable();
            $table->string('status')->nullable();
            $table->text('error')->nullable();
            $table->unsignedBigInteger('hit_count')->default(0);
            $table->timestamps();
        });
    }

    //  Route could be group. that means a group of route will be included that hit counts.

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('route_hits');
    }
};
