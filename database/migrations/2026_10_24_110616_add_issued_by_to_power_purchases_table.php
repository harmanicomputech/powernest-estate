<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Staff member who issued a token without payment (gateway "office"); null for paid purchases.
     */
    public function up(): void
    {
        Schema::table('power_purchases', function (Blueprint $table) {
            $table->foreignId('issued_by')->nullable()->after('user_id')->constrained('users')->nullOnDelete();
            $table->string('office_note')->nullable()->after('failure_reason');
        });
    }

    public function down(): void
    {
        Schema::table('power_purchases', function (Blueprint $table) {
            $table->dropConstrainedForeignId('issued_by');
            $table->dropColumn('office_note');
        });
    }
};
