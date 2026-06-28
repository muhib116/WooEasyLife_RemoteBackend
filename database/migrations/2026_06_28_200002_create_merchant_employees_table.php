<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('merchant_employees', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('merchant_user_id');
            $table->unsignedBigInteger('role_id');
            $table->unsignedBigInteger('website_id')->nullable();
            $table->string('name');
            $table->string('email')->nullable();
            $table->string('phone')->nullable();
            $table->boolean('status')->default(true);
            $table->text('notes')->nullable();
            $table->unsignedBigInteger('created_by')->nullable();
            $table->unsignedBigInteger('updated_by')->nullable();
            $table->timestamps();

            $table->index(['merchant_user_id', 'status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('merchant_employees');
    }
};
