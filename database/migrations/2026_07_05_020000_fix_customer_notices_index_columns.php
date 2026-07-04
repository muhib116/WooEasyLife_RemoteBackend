<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('customer_notices')) {
            return;
        }

        Schema::table('customer_notices', function (Blueprint $table) {
            $table->string('type', 32)->default('general')->change();
            $table->string('severity', 16)->default('info')->change();
            $table->string('audience', 32)->default('all')->change();
            $table->string('cta_url', 512)->nullable()->change();
        });

        $indexExists = collect(
            DB::select("SHOW INDEX FROM customer_notices WHERE Key_name = 'customer_notices_is_active_audience_index'")
        )->isNotEmpty();

        if (! $indexExists) {
            Schema::table('customer_notices', function (Blueprint $table) {
                $table->index(['is_active', 'audience']);
            });
        }
    }

    public function down(): void
    {
        if (! Schema::hasTable('customer_notices')) {
            return;
        }

        Schema::table('customer_notices', function (Blueprint $table) {
            $table->dropIndex(['is_active', 'audience']);
            $table->string('type')->default('general')->change();
            $table->string('severity')->default('info')->change();
            $table->string('audience')->default('all')->change();
            $table->string('cta_url')->nullable()->change();
        });
    }
};
