<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            if (! Schema::hasColumn('users', 'acquisition_source')) {
                $table->string('acquisition_source', 64)->nullable()->after('role');
                $table->index('acquisition_source');
            }
        });

        Schema::table('subscription_inquiries', function (Blueprint $table) {
            if (! Schema::hasColumn('subscription_inquiries', 'converted_at')) {
                $table->timestamp('converted_at')->nullable()->after('status');
            }
        });
    }

    public function down(): void
    {
        Schema::table('subscription_inquiries', function (Blueprint $table) {
            if (Schema::hasColumn('subscription_inquiries', 'converted_at')) {
                $table->dropColumn('converted_at');
            }
        });

        Schema::table('users', function (Blueprint $table) {
            if (Schema::hasColumn('users', 'acquisition_source')) {
                $table->dropIndex(['acquisition_source']);
                $table->dropColumn('acquisition_source');
            }
        });
    }
};
