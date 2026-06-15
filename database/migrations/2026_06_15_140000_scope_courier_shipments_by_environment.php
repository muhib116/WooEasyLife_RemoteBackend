<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('courier_shipments', function (Blueprint $table) {
            $table->dropUnique('courier_shipments_partner_consignment');
            $table->unique(
                ['partner', 'consignment_id', 'environment'],
                'courier_shipments_partner_consignment_env'
            );
        });
    }

    public function down(): void
    {
        Schema::table('courier_shipments', function (Blueprint $table) {
            $table->dropUnique('courier_shipments_partner_consignment_env');
            $table->unique(['partner', 'consignment_id'], 'courier_shipments_partner_consignment');
        });
    }
};
