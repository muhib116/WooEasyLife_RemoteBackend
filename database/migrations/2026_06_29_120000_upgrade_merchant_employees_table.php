<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('merchant_employees', function (Blueprint $table) {
            $table->text('address')->nullable()->after('phone');
            $table->string('photo')->nullable()->after('address');
        });

        Schema::create('merchant_employee_website', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('merchant_employee_id');
            $table->unsignedBigInteger('website_id');
            $table->timestamps();

            $table->unique(['merchant_employee_id', 'website_id'], 'merchant_employee_website_unique');
            $table->index('website_id');
        });

        \App\Models\MerchantEmployee::query()
            ->whereNotNull('website_id')
            ->orderBy('id')
            ->get()
            ->each(function ($employee) {
                DB::table('merchant_employee_website')->insertOrIgnore([
                    'merchant_employee_id' => $employee->id,
                    'website_id' => $employee->website_id,
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);
            });
    }

    public function down(): void
    {
        Schema::dropIfExists('merchant_employee_website');

        Schema::table('merchant_employees', function (Blueprint $table) {
            $table->dropColumn(['address', 'photo']);
        });
    }
};
