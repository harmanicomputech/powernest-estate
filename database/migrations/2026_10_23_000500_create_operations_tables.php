<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('assets', function (Blueprint $table) {
            $table->id();
            $table->foreignId('estate_id')->constrained()->cascadeOnDelete();
            $table->string('name');
            $table->string('category', 40);
            $table->string('location')->nullable();
            $table->string('serial_number')->nullable();
            $table->string('status', 20)->default('operational');
            $table->unsignedSmallInteger('service_interval_days')->nullable();
            $table->date('last_serviced_on')->nullable();
            $table->date('next_service_due_on')->nullable();
            $table->timestamps();
        });

        Schema::create('maintenance_requests', function (Blueprint $table) {
            $table->id();
            $table->foreignId('estate_id')->constrained()->cascadeOnDelete();
            $table->string('reference', 20)->unique();
            $table->foreignId('property_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('asset_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('requested_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('assigned_to')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('vendor_id')->nullable()->constrained()->nullOnDelete();
            $table->string('category', 30);
            $table->string('location')->nullable();
            $table->string('title');
            $table->text('description');
            $table->string('priority', 10)->default('normal');
            $table->string('status', 20)->default('submitted');
            $table->string('preferred_time')->nullable();
            $table->timestamp('due_at')->nullable();
            $table->timestamp('scheduled_for')->nullable();
            $table->timestamp('completed_at')->nullable();
            $table->timestamp('closed_at')->nullable();
            $table->unsignedBigInteger('labour_cost')->default(0);
            $table->unsignedBigInteger('parts_cost')->default(0);
            $table->boolean('is_preventive')->default(false);
            $table->timestamps();

            $table->index(['estate_id', 'status']);
            $table->index(['assigned_to', 'status']);
        });

        Schema::create('maintenance_updates', function (Blueprint $table) {
            $table->id();
            $table->foreignId('maintenance_request_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $table->string('type', 20);
            $table->text('body')->nullable();
            $table->boolean('is_internal')->default(false);
            $table->json('meta')->nullable();
            $table->timestamp('created_at')->useCurrent();
        });

        Schema::create('facilities', function (Blueprint $table) {
            $table->id();
            $table->foreignId('estate_id')->constrained()->cascadeOnDelete();
            $table->string('name');
            $table->text('description')->nullable();
            $table->unsignedSmallInteger('capacity')->nullable();
            $table->boolean('requires_approval')->default(true);
            $table->unsignedBigInteger('fee')->default(0);
            $table->time('opens_at')->default('07:00');
            $table->time('closes_at')->default('21:00');
            $table->unsignedSmallInteger('max_duration_minutes')->default(180);
            $table->unsignedSmallInteger('advance_booking_days')->default(30);
            $table->text('rules')->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });

        Schema::create('bookings', function (Blueprint $table) {
            $table->id();
            $table->foreignId('estate_id')->constrained()->cascadeOnDelete();
            $table->foreignId('facility_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->foreignId('property_id')->nullable()->constrained()->nullOnDelete();
            $table->timestamp('starts_at');
            $table->timestamp('ends_at');
            $table->unsignedSmallInteger('guests')->default(1);
            $table->string('purpose')->nullable();
            $table->string('status', 20);
            $table->unsignedBigInteger('fee')->default(0);
            $table->foreignId('reviewed_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('reviewed_at')->nullable();
            $table->string('review_note')->nullable();
            $table->timestamps();

            $table->index(['facility_id', 'starts_at', 'ends_at']);
        });

        Schema::create('announcements', function (Blueprint $table) {
            $table->id();
            $table->foreignId('estate_id')->constrained()->cascadeOnDelete();
            $table->foreignId('author_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('title');
            $table->text('body');
            $table->string('category', 30)->default('general');
            $table->string('audience', 20)->default('estate');
            $table->foreignId('building_id')->nullable()->constrained()->nullOnDelete();
            $table->boolean('is_pinned')->default(false);
            $table->timestamp('published_at')->nullable();
            $table->timestamp('expires_at')->nullable();
            $table->timestamps();

            $table->index(['estate_id', 'published_at']);
        });

        Schema::create('announcement_reads', function (Blueprint $table) {
            $table->id();
            $table->foreignId('announcement_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->timestamp('read_at');

            $table->unique(['announcement_id', 'user_id']);
        });

        Schema::create('staff_tasks', function (Blueprint $table) {
            $table->id();
            $table->foreignId('estate_id')->constrained()->cascadeOnDelete();
            $table->string('title');
            $table->text('description')->nullable();
            $table->foreignId('assigned_to')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->string('priority', 10)->default('normal');
            $table->string('status', 20)->default('open');
            $table->timestamp('due_at')->nullable();
            $table->timestamp('completed_at')->nullable();
            $table->timestamps();

            $table->index(['estate_id', 'status']);
        });
    }

    public function down(): void
    {
        foreach (['staff_tasks', 'announcement_reads', 'announcements', 'bookings', 'facilities', 'maintenance_updates', 'maintenance_requests', 'assets'] as $table) {
            Schema::dropIfExists($table);
        }
    }
};
