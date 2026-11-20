<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('facilities', function (Blueprint $table) {
            $table->id();
            $table->string('name_tr');
            $table->string('name_en');
            $table->text('description_tr')->nullable();
            $table->text('description_en')->nullable();
            $table->unsignedTinyInteger('slot_minutes')->default(60);
            $table->unsignedSmallInteger('capacity')->default(1);
            $table->boolean('group_allowed')->default(false);
            $table->boolean('participant_list_required')->default(false);
            $table->unsignedSmallInteger('cancel_deadline_hours')->default(2);
            $table->json('allowed_roles');
            $table->char('checkin_token', 40)->unique();
            $table->boolean('is_active')->default(true);
            $table->unsignedSmallInteger('sort')->default(0);
            $table->timestamps();
        });

        Schema::create('facility_units', function (Blueprint $table) {
            $table->id();
            $table->foreignId('facility_id')->constrained()->cascadeOnDelete();
            $table->string('name_tr');
            $table->string('name_en');
            $table->unsignedSmallInteger('sort')->default(0);
            $table->timestamps();
        });

        Schema::create('activity_types', function (Blueprint $table) {
            $table->id();
            $table->foreignId('facility_id')->constrained()->cascadeOnDelete();
            $table->string('name_tr');
            $table->string('name_en');
            $table->unsignedTinyInteger('units_required')->default(1);
            $table->boolean('is_team')->default(false);
            $table->json('allowed_roles');
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });

        Schema::create('facility_hours', function (Blueprint $table) {
            $table->id();
            $table->foreignId('facility_id')->constrained()->cascadeOnDelete();
            $table->unsignedTinyInteger('weekday'); // 1 = Pazartesi … 7 = Pazar
            $table->boolean('is_closed')->default(false);
            $table->time('opens_at')->nullable();
            $table->time('closes_at')->nullable();
            $table->unique(['facility_id', 'weekday']);
        });

        Schema::create('facility_rules', function (Blueprint $table) {
            $table->id();
            $table->foreignId('facility_id')->constrained()->cascadeOnDelete();
            $table->string('role', 20)->nullable(); // null = tesis varsayılanı
            $table->unsignedSmallInteger('max_duration_minutes')->nullable();
            $table->unsignedTinyInteger('max_per_day')->nullable();
            $table->unsignedTinyInteger('max_per_week')->nullable();
            $table->unsignedSmallInteger('max_days_ahead')->nullable();
            $table->unsignedSmallInteger('min_hours_before')->nullable();
            $table->timestamps();
            $table->unique(['facility_id', 'role']);
        });

        Schema::create('facility_approvers', function (Blueprint $table) {
            $table->foreignId('facility_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->primary(['facility_id', 'user_id']);
        });

        Schema::create('holidays', function (Blueprint $table) {
            $table->id();
            $table->date('date')->unique();
            $table->string('name_tr');
            $table->string('name_en');
            $table->time('closed_from')->nullable(); // null = tam gün
            $table->timestamps();
        });

        Schema::create('closures', function (Blueprint $table) {
            $table->id();
            $table->foreignId('facility_id')->nullable()->constrained()->cascadeOnDelete();
            $table->dateTime('starts_at');
            $table->dateTime('ends_at');
            $table->string('reason');
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->index(['starts_at', 'ends_at']);
        });

        Schema::create('equipment', function (Blueprint $table) {
            $table->id();
            $table->string('name_tr');
            $table->string('name_en');
            $table->unsignedSmallInteger('total_stock')->default(0);
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });

        Schema::create('equipment_facility', function (Blueprint $table) {
            $table->foreignId('equipment_id')->constrained('equipment')->cascadeOnDelete();
            $table->foreignId('facility_id')->constrained()->cascadeOnDelete();
            $table->primary(['equipment_id', 'facility_id']);
        });
    }

    public function down(): void
    {
        foreach (['equipment_facility', 'equipment', 'closures', 'holidays', 'facility_approvers', 'facility_rules',
            'facility_hours', 'activity_types', 'facility_units', 'facilities'] as $t) {
            Schema::dropIfExists($t);
        }
    }
};
