<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('recurring_blocks', function (Blueprint $table) {
            $table->id();
            $table->foreignId('facility_id')->constrained()->cascadeOnDelete();
            $table->foreignId('activity_type_id')->constrained()->cascadeOnDelete();
            $table->foreignId('team_id')->nullable()->constrained()->nullOnDelete();
            $table->string('title');
            $table->json('weekdays');
            $table->json('unit_ids');
            $table->time('start_time');
            $table->time('end_time');
            $table->date('starts_on');
            $table->date('ends_on');
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
        });

        Schema::create('reservations', function (Blueprint $table) {
            $table->id();
            $table->string('code', 20)->unique();
            $table->foreignId('facility_id')->constrained();
            $table->foreignId('activity_type_id')->constrained();
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('team_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('recurring_block_id')->nullable()->constrained()->nullOnDelete();
            $table->enum('kind', ['individual', 'group', 'team', 'block']);
            $table->enum('status', ['pending', 'approved', 'rejected', 'cancelled', 'expired'])->default('pending');
            $table->dateTime('starts_at');
            $table->dateTime('ends_at');
            $table->unsignedSmallInteger('participant_count')->default(1);
            $table->text('notes')->nullable();
            $table->foreignId('decided_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('decided_at')->nullable();
            $table->string('rejection_reason', 500)->nullable();
            $table->foreignId('cancelled_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('cancelled_at')->nullable();
            $table->string('cancel_reason', 500)->nullable();
            $table->boolean('is_late_cancel')->default(false);
            $table->enum('attendance', ['attended', 'no_show'])->nullable();
            $table->foreignId('attendance_marked_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('attendance_marked_at')->nullable();
            $table->timestamp('approver_reminded_at')->nullable();
            $table->timestamp('reminded_day_at')->nullable();
            $table->timestamp('reminded_hours_at')->nullable();
            $table->timestamp('anonymized_at')->nullable();
            $table->timestamps();
            $table->index(['facility_id', 'starts_at']);
            $table->index(['status', 'starts_at']);
            $table->index(['user_id', 'starts_at']);
        });

        Schema::create('reservation_units', function (Blueprint $table) {
            $table->id();
            $table->foreignId('reservation_id')->constrained()->cascadeOnDelete();
            $table->foreignId('facility_unit_id')->constrained()->cascadeOnDelete();
            $table->dateTime('starts_at');
            $table->dateTime('ends_at');
            $table->boolean('is_active')->default(true);
            $table->index(['facility_unit_id', 'is_active', 'starts_at', 'ends_at'], 'ru_conflict_idx');
        });

        Schema::create('reservation_participants', function (Blueprint $table) {
            $table->id();
            $table->foreignId('reservation_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->timestamp('removed_at')->nullable();
            $table->timestamps();
            $table->unique(['reservation_id', 'user_id']);
        });

        Schema::create('reservation_checkins', function (Blueprint $table) {
            $table->id();
            $table->foreignId('reservation_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $table->enum('method', ['qr', 'attendant']);
            $table->timestamp('checked_in_at');
            $table->unique(['reservation_id', 'user_id']);
        });

        Schema::create('reservation_equipment', function (Blueprint $table) {
            $table->id();
            $table->foreignId('reservation_id')->constrained()->cascadeOnDelete();
            $table->foreignId('equipment_id')->constrained('equipment');
            $table->unsignedSmallInteger('quantity');
            $table->timestamp('delivered_at')->nullable();
            $table->foreignId('delivered_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('returned_at')->nullable();
            $table->foreignId('returned_by')->nullable()->constrained('users')->nullOnDelete();
            $table->enum('return_condition', ['ok', 'damaged', 'missing'])->nullable();
            $table->string('note', 500)->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        foreach (['reservation_equipment', 'reservation_checkins', 'reservation_participants', 'reservation_units',
            'reservations', 'recurring_blocks'] as $t) {
            Schema::dropIfExists($t);
        }
    }
};
