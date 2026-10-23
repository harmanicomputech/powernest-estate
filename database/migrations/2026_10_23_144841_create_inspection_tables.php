<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('inspection_templates', function (Blueprint $table) {
            $table->id();
            $table->foreignId('estate_id')->constrained()->cascadeOnDelete();
            $table->string('name');
            $table->string('category', 30);
            $table->json('items');
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });

        Schema::create('inspections', function (Blueprint $table) {
            $table->id();
            $table->foreignId('estate_id')->constrained()->cascadeOnDelete();
            $table->foreignId('inspection_template_id')->nullable()->constrained()->nullOnDelete();
            $table->string('title');
            $table->string('location')->nullable();
            $table->foreignId('inspector_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('status', 20)->default('in_progress');
            $table->json('results');
            $table->unsignedSmallInteger('failed_count')->default(0);
            $table->text('notes')->nullable();
            $table->timestamp('completed_at')->nullable();
            $table->timestamps();

            $table->index(['estate_id', 'status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('inspections');
        Schema::dropIfExists('inspection_templates');
    }
};
