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
        Schema::table('car_price_details', function (Blueprint $table) {
            if (!Schema::hasColumn('car_price_details', 'range_type')) {
                $table->string('range_type', 32)->nullable()->after('car_id');
            }
            if (!Schema::hasColumn('car_price_details', 'driver_betta')) {
                $table->decimal('driver_betta', 10, 2)->default(0)->after('fuel_charge');
            }
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('car_price_details', function (Blueprint $table) {
            if (Schema::hasColumn('car_price_details', 'range_type')) {
                $table->dropColumn('range_type');
            }
            if (Schema::hasColumn('car_price_details', 'driver_betta')) {
                $table->dropColumn('driver_betta');
            }
        });
    }
};
