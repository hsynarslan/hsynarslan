<?php

namespace Tests\Feature;

use App\Models\Reservation;
use App\Services\BlockService;
use App\Services\CheckinToken;
use App\Services\ReservationService;
use Illuminate\Support\Carbon;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

class AttendanceSanctionTest extends TestCase
{
    private function approvedFor(string $username, string $date): Reservation
    {
        $r = $this->book($this->user($username), ['date' => $date]);
        app(ReservationService::class)->approve($this->user('sksadmin'), $r);

        return $r->fresh();
    }

    public function test_qr_checkin_marks_attendance(): void
    {
        $r = $this->approvedFor('ogrenci', '2026-11-25');
        Carbon::setTestNow(Carbon::parse('2026-11-25 09:55'));
        $this->loginAs($this->user('ogrenci'));
        $url = CheckinToken::url($this->gym());
        $this->get($url)->assertOk()->assertSee('Girişimi kaydet');
        $this->post($url)->assertOk()->assertSee('Giriş kaydedildi');
        $this->assertSame('attended', $r->fresh()->attendance);
    }

    public function test_checkin_outside_window_is_refused_and_bad_token_404(): void
    {
        $this->approvedFor('ogrenci', '2026-11-25');
        Carbon::setTestNow(Carbon::parse('2026-11-25 09:00'));
        $this->loginAs($this->user('ogrenci'));
        $this->post(CheckinToken::url($this->gym()))->assertOk()->assertSee('rezervasyonunuz yok');
        $this->post(route('checkin.store', str_repeat('x', 40)))->assertNotFound();
    }

    public function test_three_no_shows_trigger_two_week_ban(): void
    {
        $u = $this->user('ogrenci');
        foreach (['2026-11-25', '2026-11-26', '2026-11-27'] as $d) {
            $this->approvedFor('ogrenci', $d);
        }
        Carbon::setTestNow(Carbon::parse('2026-11-27 12:00'));
        $this->artisan('reservations:mark-no-shows')->assertSuccessful();

        $this->assertSame(3, Reservation::where('attendance', 'no_show')->count());
        $s = $u->activeSanction();
        $this->assertNotNull($s);
        $this->assertTrue($s->ends_at->isSameDay(now()->addDays(14)));
    }

    public function test_attendant_correction_lifts_automatic_ban(): void
    {
        $rs = collect(['2026-11-25', '2026-11-26', '2026-11-27'])->map(fn ($d) => $this->approvedFor('ogrenci', $d));
        Carbon::setTestNow(Carbon::parse('2026-11-27 12:00'));
        $this->artisan('reservations:mark-no-shows');
        $this->assertNotNull($this->user('ogrenci')->activeSanction());

        $this->loginAs($this->user('gorevli'))->post(route('attendant.attendance', $rs->last()), ['attendance' => 'attended'])->assertRedirect();
        $this->assertNull($this->user('ogrenci')->activeSanction());
    }

    public function test_coach_is_never_marked_no_show(): void
    {
        $this->approvedFor('antrenor', '2026-11-25');
        Carbon::setTestNow(Carbon::parse('2026-11-25 12:00'));
        $this->artisan('reservations:mark-no-shows');
        $this->assertSame(0, Reservation::where('attendance', 'no_show')->count());
    }

    public function test_block_creation_cancels_conflicts_and_locks_slots(): void
    {
        $r = $this->approvedFor('ogrenci', '2026-11-25');
        $gym = $this->gym();
        $block = app(BlockService::class)->create($this->user('sksadmin'), [
            'facility_id' => $gym->id, 'activity_type_id' => $this->activity('Basketbol (Takım)')->id, 'team_id' => null,
            'title' => 'Antrenman', 'weekdays' => [3], 'unit_ids' => $gym->units->pluck('id')->all(),
            'start_time' => '09:00', 'end_time' => '11:00', 'starts_on' => '2026-11-24', 'ends_on' => '2026-12-10',
        ], 'Takım antrenmanı');

        $this->assertSame(Reservation::CANCELLED, $r->fresh()->status);
        $this->assertSame(3, $block->reservations()->count()); // 25.11, 2.12, 9.12
        $this->expectException(ValidationException::class);
        $this->book($this->user('ogrenci2'), ['date' => '2026-12-02', 'start' => '10:00', 'end' => '11:00']);
    }
}
