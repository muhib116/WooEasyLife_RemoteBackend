<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('subscription_inquiries')) {
            return;
        }

        DB::statement("ALTER TABLE subscription_inquiries MODIFY domain VARCHAR(191) NOT NULL");
        DB::statement("ALTER TABLE subscription_inquiries MODIFY status VARCHAR(32) NOT NULL DEFAULT 'pending'");
        DB::statement("ALTER TABLE subscription_inquiries MODIFY source VARCHAR(64) NOT NULL DEFAULT 'landing_pricing'");

        $indexes = collect(DB::select('SHOW INDEX FROM subscription_inquiries'))
            ->pluck('Key_name')
            ->unique();

        Schema::table('subscription_inquiries', function (Blueprint $table) use ($indexes) {
            if (! $indexes->contains('subscription_inquiries_status_created_at_index')) {
                $table->index(['status', 'created_at']);
            }

            if (! $indexes->contains('subscription_inquiries_domain_index')) {
                $table->index('domain');
            }
        });
    }

    public function down(): void
    {
        if (! Schema::hasTable('subscription_inquiries')) {
            return;
        }

        Schema::table('subscription_inquiries', function (Blueprint $table) {
            $table->dropIndex(['status', 'created_at']);
            $table->dropIndex(['domain']);
        });
    }
};
