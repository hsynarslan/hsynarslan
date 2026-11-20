<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('teams', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('sport')->nullable();
            $table->foreignId('coach_id')->nullable()->constrained('users')->nullOnDelete();
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });

        // Hassas alanlar "text" tutulur çünkü uygulama katmanında şifrelenir (ISO 27001 A.8.24).
        Schema::create('team_players', function (Blueprint $table) {
            $table->id();
            $table->foreignId('team_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('jersey_no', 5)->nullable();
            $table->string('position', 50)->nullable();
            $table->text('birth_date')->nullable();
            $table->text('phone')->nullable();
            $table->text('license_no')->nullable();
            $table->text('health_report_date')->nullable();
            $table->text('health_report_expires_on')->nullable();
            $table->text('emergency_contact_name')->nullable();
            $table->text('emergency_contact_phone')->nullable();
            $table->text('notes')->nullable();
            $table->string('photo_path')->nullable();
            $table->date('joined_on');
            $table->date('left_on')->nullable();
            $table->timestamp('health_warned_at')->nullable();
            $table->timestamp('sensitive_purged_at')->nullable();
            $table->timestamps();
            $table->index(['team_id', 'left_on']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('team_players');
        Schema::dropIfExists('teams');
    }
};
