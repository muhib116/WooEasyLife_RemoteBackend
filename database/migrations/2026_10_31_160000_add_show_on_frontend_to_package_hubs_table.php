<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('package_hubs') || Schema::hasColumn('package_hubs', 'show_on_frontend')) {
            return;
        }

        $afterSpecial = Schema::hasColumn('package_hubs', 'is_special');

        Schema::table('package_hubs', function (Blueprint $table) use ($afterSpecial) {
            $column = $table->boolean('show_on_frontend')->default(true);

            if ($afterSpecial) {
                $column->after('is_special');
            }
        });
    }

    public function down(): void
    {
        if (! Schema::hasTable('package_hubs') || ! Schema::hasColumn('package_hubs', 'show_on_frontend')) {
            return;
        }

        Schema::table('package_hubs', function (Blueprint $table) {
            $table->dropColumn('show_on_frontend');
        });
    }
};
