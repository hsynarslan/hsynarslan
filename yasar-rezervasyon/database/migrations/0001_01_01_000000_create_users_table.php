<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Şifre sütunu yoktur: kimlik doğrulama yalnızca LDAP'ta yapılır (ISO 27001 A.5.17).
        Schema::create('users', function (Blueprint $table) {
            $table->id();
            $table->string('username', 100)->unique();
            $table->string('ldap_dn', 500)->nullable();
            $table->string('name');
            $table->string('email')->nullable();
            $table->string('number', 50)->nullable()->index();
            $table->string('department')->nullable();
            $table->enum('base_role', ['student', 'admin_staff', 'academic', 'alumni']);
            $table->boolean('is_coach')->default(false);
            $table->boolean('is_sks_admin')->default(false);
            $table->boolean('is_attendant')->default(false);
            $table->enum('locale', ['tr', 'en'])->default('tr');
            $table->boolean('is_active')->default(true);
            $table->timestamp('last_login_at')->nullable();
            $table->timestamps();
        });

        Schema::create('sessions', function (Blueprint $table) {
            $table->string('id')->primary();
            $table->foreignId('user_id')->nullable()->index();
            $table->string('ip_address', 45)->nullable();
            $table->text('user_agent')->nullable();
            $table->longText('payload');
            $table->integer('last_activity')->index();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('sessions');
        Schema::dropIfExists('users');
    }
};
