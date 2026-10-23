<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('meters', function (Blueprint $table) {
            $table->id();
            $table->foreignId('estate_id')->constrained()->cascadeOnDelete();
            $table->foreignId('property_id')->constrained()->cascadeOnDelete();
            $table->string('meter_number', 40);
            $table->string('label')->nullable();
            $table->string('meter_type', 60)->nullable();
            $table->string('vendor_customer_name')->nullable();
            $table->timestamp('validated_at')->nullable();
            $table->string('status', 20)->default('active');
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->unique(['estate_id', 'meter_number']);
        });

        Schema::create('meter_favourites', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->foreignId('meter_id')->constrained()->cascadeOnDelete();
            $table->timestamps();

            $table->unique(['user_id', 'meter_id']);
        });

        Schema::create('power_purchases', function (Blueprint $table) {
            $table->id();
            $table->string('reference', 32)->unique();
            $table->foreignId('estate_id')->constrained()->restrictOnDelete();
            $table->foreignId('user_id')->constrained()->restrictOnDelete();
            $table->foreignId('meter_id')->constrained()->restrictOnDelete();
            $table->foreignId('property_id')->constrained()->restrictOnDelete();
            $table->string('meter_number', 40);
            $table->string('gateway', 20);
            $table->char('currency', 3)->default('NGN');
            // All amounts in kobo.
            $table->unsignedBigInteger('vend_amount');
            $table->unsignedBigInteger('service_charge');
            $table->unsignedBigInteger('total_amount');
            $table->unsignedBigInteger('amount_paid')->nullable();
            $table->string('status', 30)->index();
            $table->string('gateway_reference', 100)->nullable();
            $table->string('payment_channel', 40)->nullable();
            $table->string('payment_confirmed_via', 20)->nullable();
            $table->timestamp('paid_at')->nullable();
            $table->unsignedTinyInteger('vend_attempts')->default(0);
            $table->timestamp('last_vend_attempt_at')->nullable();
            $table->timestamp('token_issued_at')->nullable();
            $table->string('failure_code', 40)->nullable();
            $table->string('failure_reason')->nullable();
            $table->timestamp('last_verified_at')->nullable();
            $table->timestamps();

            $table->index(['estate_id', 'status', 'created_at']);
            $table->index(['user_id', 'created_at']);
        });

        Schema::create('power_tokens', function (Blueprint $table) {
            $table->id();
            // One token per purchase, enforced by the database.
            $table->foreignId('power_purchase_id')->unique()->constrained()->restrictOnDelete();
            $table->text('token');
            $table->decimal('units', 12, 2)->nullable();
            $table->string('unit_label', 10)->default('kWh');
            $table->string('meter_number', 40);
            $table->string('source', 20)->default('vendor');
            $table->json('meta')->nullable();
            $table->foreignId('recorded_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('issued_at');
            $table->timestamps();
        });

        Schema::create('vend_attempts', function (Blueprint $table) {
            $table->id();
            $table->foreignId('power_purchase_id')->constrained()->cascadeOnDelete();
            $table->unsignedTinyInteger('attempt');
            $table->string('outcome', 20)->nullable();
            $table->unsignedSmallInteger('http_status')->nullable();
            $table->string('error')->nullable();
            $table->json('response')->nullable();
            $table->foreignId('initiated_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('started_at');
            $table->timestamp('finished_at')->nullable();

            $table->unique(['power_purchase_id', 'attempt']);
        });

        Schema::create('power_purchase_events', function (Blueprint $table) {
            $table->id();
            $table->foreignId('power_purchase_id')->constrained()->cascadeOnDelete();
            $table->string('from_status', 30)->nullable();
            $table->string('to_status', 30);
            $table->foreignId('actor_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('note')->nullable();
            $table->timestamp('created_at')->useCurrent();
        });

        Schema::create('gateway_webhook_events', function (Blueprint $table) {
            $table->id();
            $table->string('gateway', 20);
            $table->char('fingerprint', 64)->unique();
            $table->string('reference', 100)->nullable()->index();
            $table->string('event_type', 60)->nullable();
            $table->boolean('signature_valid')->default(false);
            $table->json('payload');
            $table->string('status', 20)->default('received');
            $table->string('error')->nullable();
            $table->timestamp('processed_at')->nullable();
            $table->timestamps();
        });

        Schema::create('support_tickets', function (Blueprint $table) {
            $table->id();
            $table->foreignId('estate_id')->constrained()->cascadeOnDelete();
            $table->string('reference', 20)->unique();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('category', 20);
            $table->foreignId('power_purchase_id')->nullable()->constrained()->nullOnDelete();
            $table->string('subject');
            $table->string('status', 20)->default('open');
            $table->foreignId('assigned_to')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('resolved_at')->nullable();
            $table->timestamps();
        });

        Schema::create('support_ticket_messages', function (Blueprint $table) {
            $table->id();
            $table->foreignId('support_ticket_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $table->text('body');
            $table->boolean('is_staff')->default(false);
            $table->timestamp('created_at')->useCurrent();
        });
    }

    public function down(): void
    {
        foreach (['support_ticket_messages', 'support_tickets', 'gateway_webhook_events', 'power_purchase_events', 'vend_attempts', 'power_tokens', 'power_purchases', 'meter_favourites', 'meters'] as $table) {
            Schema::dropIfExists($table);
        }
    }
};
