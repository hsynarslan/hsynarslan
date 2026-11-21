<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Salon görevlisi yalnızca atandığı tesislerde işlem yapabilir (ISO 27001 A.5.15, A.8.3).
        Schema::create('facility_attendants', function (Blueprint $table) {
            $table->foreignId('facility_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->primary(['facility_id', 'user_id']);
        });

        // dynamic: girişteki tabletin gösterdiği, 30 saniyede bir değişen imzalı QR. static: basılı sabit QR.
        Schema::table('facilities', function (Blueprint $table) {
            $table->string('checkin_mode', 10)->default('dynamic')->after('checkin_token');
        });
    }

    public function down(): void
    {
        Schema::table('facilities', fn (Blueprint $t) => $t->dropColumn('checkin_mode'));
        Schema::dropIfExists('facility_attendants');
    }
};
