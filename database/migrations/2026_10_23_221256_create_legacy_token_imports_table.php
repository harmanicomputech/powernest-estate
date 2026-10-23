<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Holding area for electricity tokens imported with plain SQL from the legacy PowerNest app.
     * The scheduler moves each row into power_tokens (encrypted with this site's key) and deletes it.
     */
    public function up(): void
    {
        if (Schema::hasTable('legacy_token_imports')) {
            return;
        }

        Schema::create('legacy_token_imports', function (Blueprint $table) {
            $table->id();
            $table->string('purchase_reference', 32)->unique();
            $table->string('token', 100);
            $table->decimal('units', 12, 2)->nullable();
            $table->string('meter_number', 40);
            $table->json('meta')->nullable();
            $table->timestamp('issued_at');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('legacy_token_imports');
    }
};
