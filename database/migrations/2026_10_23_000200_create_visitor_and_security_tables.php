<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('gates', function (Blueprint $table) {
            $table->id();
            $table->foreignId('estate_id')->constrained()->cascadeOnDelete();
            $table->string('name');
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });

        Schema::create('visitor_passes', function (Blueprint $table) {
            $table->id();
            $table->foreignId('estate_id')->constrained()->cascadeOnDelete();
            $table->foreignId('property_id')->constrained()->cascadeOnDelete();
            $table->foreignId('host_user_id')->constrained('users')->cascadeOnDelete();
            // Lookup hashes: the QR token and numeric code are never stored in plain text.
            $table->char('token_lookup', 64)->unique();
            $table->text('token_encrypted');
            $table->char('code_lookup', 64)->nullable()->unique();
            $table->text('code_encrypted');
            $table->string('visitor_name');
            $table->string('visitor_phone', 32)->nullable();
            $table->string('visitor_email')->nullable();
            $table->string('category', 30);
            $table->string('pass_type', 20);
            $table->unsignedSmallInteger('group_size')->default(1);
            $table->string('vehicle_plate', 20)->nullable();
            $table->timestamp('valid_from');
            $table->timestamp('valid_until');
            $table->json('recurrence')->nullable();
            $table->unsignedSmallInteger('max_entries')->nullable();
            $table->unsignedSmallInteger('entries_count')->default(0);
            $table->string('status', 20)->default('active');
            $table->text('notes')->nullable();
            $table->timestamp('revoked_at')->nullable();
            $table->foreignId('revoked_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->index(['estate_id', 'status', 'valid_from']);
            $table->index(['host_user_id', 'status']);
        });

        Schema::create('visits', function (Blueprint $table) {
            $table->id();
            $table->foreignId('estate_id')->constrained()->cascadeOnDelete();
            $table->foreignId('visitor_pass_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('property_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('host_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('gate_id')->nullable()->constrained()->nullOnDelete();
            $table->string('source', 20);
            $table->string('status', 20);
            $table->string('visitor_name');
            $table->string('visitor_phone', 32)->nullable();
            $table->string('category', 30);
            $table->unsignedSmallInteger('group_size')->default(1);
            $table->string('vehicle_plate', 20)->nullable();
            $table->string('purpose')->nullable();
            $table->string('denial_reason')->nullable();
            $table->timestamp('host_responded_at')->nullable();
            $table->timestamp('checked_in_at')->nullable();
            $table->foreignId('checked_in_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('checked_out_at')->nullable();
            $table->foreignId('checked_out_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('recorded_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->index(['estate_id', 'status']);
            $table->index(['estate_id', 'created_at']);
        });

        Schema::create('pass_scans', function (Blueprint $table) {
            $table->id();
            $table->foreignId('estate_id')->constrained()->cascadeOnDelete();
            $table->foreignId('visitor_pass_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('scanned_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('gate_id')->nullable()->constrained()->nullOnDelete();
            $table->string('method', 10);
            $table->string('result', 30);
            $table->timestamp('created_at')->useCurrent();

            $table->index(['estate_id', 'created_at']);
        });

        Schema::create('security_shifts', function (Blueprint $table) {
            $table->id();
            $table->foreignId('estate_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->foreignId('gate_id')->nullable()->constrained()->nullOnDelete();
            $table->timestamp('started_at');
            $table->timestamp('ended_at')->nullable();
            $table->text('handover_notes')->nullable();
            $table->timestamps();

            $table->index(['estate_id', 'ended_at']);
        });

        Schema::create('incidents', function (Blueprint $table) {
            $table->id();
            $table->foreignId('estate_id')->constrained()->cascadeOnDelete();
            $table->string('reference', 20)->unique();
            $table->foreignId('reported_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('property_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('assigned_to')->nullable()->constrained('users')->nullOnDelete();
            $table->string('category', 30);
            $table->string('severity', 20);
            $table->string('status', 20)->default('open');
            $table->string('title');
            $table->text('description');
            $table->string('location')->nullable();
            $table->timestamp('occurred_at');
            $table->timestamp('resolved_at')->nullable();
            $table->timestamp('closed_at')->nullable();
            $table->text('resolution')->nullable();
            $table->timestamps();

            $table->index(['estate_id', 'status']);
        });

        Schema::create('incident_updates', function (Blueprint $table) {
            $table->id();
            $table->foreignId('incident_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $table->string('type', 20);
            $table->text('body')->nullable();
            $table->json('meta')->nullable();
            $table->timestamp('created_at')->useCurrent();
        });

        Schema::create('lost_found_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('estate_id')->constrained()->cascadeOnDelete();
            $table->string('type', 10);
            $table->string('title');
            $table->text('description')->nullable();
            $table->string('location')->nullable();
            $table->string('status', 20)->default('open');
            $table->foreignId('reported_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('resolved_at')->nullable();
            $table->timestamps();
        });

        Schema::create('emergency_contacts', function (Blueprint $table) {
            $table->id();
            $table->foreignId('estate_id')->constrained()->cascadeOnDelete();
            $table->string('name');
            $table->string('role')->nullable();
            $table->string('phone', 32);
            $table->unsignedSmallInteger('sort_order')->default(0);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        foreach (['emergency_contacts', 'lost_found_items', 'incident_updates', 'incidents', 'security_shifts', 'pass_scans', 'visits', 'visitor_passes', 'gates'] as $table) {
            Schema::dropIfExists($table);
        }
    }
};
