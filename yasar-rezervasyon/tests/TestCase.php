<?php

namespace Tests;

use App\Models\ActivityType;
use App\Models\Facility;
use App\Models\KvkkConsent;
use App\Models\KvkkText;
use App\Models\User;
use App\Services\ReservationService;
use Database\Seeders\DemoSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Foundation\Testing\TestCase as BaseTestCase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Mail;

abstract class TestCase extends BaseTestCase
{
    use RefreshDatabase;

    /** Pazartesi 23.11.2026 08:00 */
    protected Carbon $now;

    protected function setUp(): void
    {
        parent::setUp();
        $this->now = Carbon::parse('2026-11-23 08:00');
        Carbon::setTestNow($this->now);
        Mail::fake();
        $this->seed(DemoSeeder::class);
    }

    protected function user(string $username): User
    {
        return User::where('username', $username)->firstOrFail();
    }

    protected function gym(): Facility
    {
        return Facility::where('name_tr', 'Spor Salonu')->firstOrFail();
    }

    protected function activity(string $name): ActivityType
    {
        return ActivityType::where('name_tr', $name)->firstOrFail();
    }

    protected function book(User $user, array $override = [])
    {
        return app(ReservationService::class)->create($user, array_merge([
            'facility_id' => $this->gym()->id,
            'activity_type_id' => $this->activity('Basketbol (Bireysel)')->id,
            'date' => '2026-11-25',
            'start' => '10:00',
            'end' => '11:00',
        ], $override));
    }

    /** KVKK onayı verilmiş oturum. */
    protected function loginAs(User $user): static
    {
        KvkkConsent::firstOrCreate(['user_id' => $user->id, 'kvkk_text_id' => KvkkText::current()->id], ['accepted_at' => now()]);

        return $this->actingAs($user);
    }
}
