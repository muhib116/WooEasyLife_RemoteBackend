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
        Schema::create('package_use_histories', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('user_id');
            $table->unsignedBigInteger('user_package_id');
            $table->json('use_details')->nullable(); // user order related information thakbe proman hisebe
            $table->integer('order_count'); // koyda order ek sathe use hoice
            $table->double('cost'); // total koto cost hoice
            $table->integer('total_order_handled'); // ei package e current use soho kotota order handle hoice
            $table->integer('remaining_order'); // ei package e r koyda order baki ace
            $table->unsignedBigInteger('created_by')->nullable();
            $table->unsignedBigInteger('updated_by')->nullable();
            $table->softDeletes();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('package_use_histories');
    }
};
