<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * The electricity service charge is ₦350. Estates that saved the old ₦250 default are moved to ₦350;
     * estates without a saved value already pick up the new default from config.
     */
    public function up(): void
    {
        DB::table('estates')->whereNotNull('settings')->orderBy('id')->each(function (object $estate) {
            $settings = json_decode($estate->settings, true) ?: [];

            if ((int) data_get($settings, 'power.service_charge') === 25_000) {
                data_set($settings, 'power.service_charge', 35_000);
                DB::table('estates')->where('id', $estate->id)->update(['settings' => json_encode($settings)]);
            }
        });
    }

    public function down(): void {}
};
