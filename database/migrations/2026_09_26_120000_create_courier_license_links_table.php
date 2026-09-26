<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('courier_license_links', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('user_id');
            $table->unsignedBigInteger('access_token_id');
            $table->string('slug', 32);
            $table->unsignedBigInteger('courier_configuration_id');
            $table->boolean('synced')->default(false);
            $table->timestamps();

            $table->unique(['access_token_id', 'slug'], 'courier_license_links_token_slug');
            $table->index(['user_id', 'slug']);
        });

        Schema::table('courier_configurations', function (Blueprint $table) {
            $table->unsignedBigInteger('access_token_id')->nullable()->after('user_id');
            $table->index(['user_id', 'access_token_id'], 'courier_configurations_user_token');
        });

        app(\App\Services\Courier\CourierLicenseSyncService::class)->backfillExistingLinks();
    }

    public function down(): void
    {
        Schema::table('courier_configurations', function (Blueprint $table) {
            $table->dropIndex('courier_configurations_user_token');
            $table->dropColumn('access_token_id');
        });

        Schema::dropIfExists('courier_license_links');
    }
};
