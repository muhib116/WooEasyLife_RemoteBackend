<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('package_payment_requests', function (Blueprint $table) {
            $table->string('payment_intent', 32)
                ->nullable()
                ->after('note')
                ->comment('subscribe, renew, upgrade, or downgrade');
        });
    }

    public function down(): void
    {
        Schema::table('package_payment_requests', function (Blueprint $table) {
            $table->dropColumn('payment_intent');
        });
    }
};
