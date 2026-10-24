<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Residents can buy for any verified meter by number, so a meter (and its purchases)
     * no longer has to belong to a property. The vendor's address is kept for display.
     */
    public function up(): void
    {
        Schema::table('meters', function (Blueprint $table) {
            $table->unsignedBigInteger('property_id')->nullable()->change();
            $table->string('vendor_address')->nullable()->after('vendor_customer_name');
        });

        Schema::table('power_purchases', function (Blueprint $table) {
            $table->unsignedBigInteger('property_id')->nullable()->change();
        });
    }

    public function down(): void
    {
        Schema::table('meters', function (Blueprint $table) {
            $table->dropColumn('vendor_address');
        });
    }
};
