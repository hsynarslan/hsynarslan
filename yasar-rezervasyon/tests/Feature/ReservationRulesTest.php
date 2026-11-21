<?php

namespace Tests\Feature;

use App\Models\Closure;
use App\Models\Equipment;
use App\Models\Facility;
use App\Models\Holiday;
use App\Models\Reservation;
use App\Models\Sanction;
use App\Models\Team;
use App\Services\ReservationService;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

class ReservationRulesTest extends TestCase
{
    private function expectRule(callable $fn, string $contains): void
    {
        try {
            $fn();
            $this->fail('ValidationException bekleniyordu: '.$contains);
        } catch (ValidationException $e) {
            $this->assertStringContainsString($contains, collect($e->errors())->flatten()->join(' '));
        }
    }

    public function test_individual_basketball_books_one_hoop_and_second_user_gets_the_other(): void
    {
        $a = $this->book($this->user('ogrenci'));
        $b = $this->book($this->user('ogrenci2'));

        $this->assertSame(Reservation::PENDING, $a->status);
        $this->assertCount(1, $a->units);
        $this->assertCount(1, $b->units);
        $this->assertNotEquals($a->units->first()->id, $b->units->first()->id);

        $this->expectRule(fn () => $this->book($this->user('ogrenci3')), 'yeterli boş alan yok');
    }

    public function test_volleyball_needs_whole_hall(): void
    {
        $this->book($this->user('ogrenci'));
        $this->expectRule(fn () => $this->book($this->user('akademik'), ['activity_type_id' => $this->activity('Voleybol')->id]), 'yeterli boş alan yok');

        $v = $this->book($this->user('akademik'), ['activity_type_id' => $this->activity('Voleybol')->id, 'start' => '12:00', 'end' => '13:00']);
        $this->assertCount(2, $v->units);
        $this->expectRule(fn () => $this->book($this->user('ogrenci2'), ['start' => '12:00', 'end' => '13:00']), 'yeterli boş alan yok');
    }

    public function test_students_cannot_book_volleyball_or_team_basketball(): void
    {
        $this->expectRule(fn () => $this->book($this->user('ogrenci'), ['activity_type_id' => $this->activity('Voleybol')->id]), 'yetkiniz yok');
        $this->expectRule(fn () => $this->book($this->user('ogrenci'), ['activity_type_id' => $this->activity('Basketbol (Takım)')->id]), 'yetkiniz yok');
    }

    public function test_team_basketball_requires_team_and_books_two_hoops(): void
    {
        $coach = $this->user('antrenor');
        $this->expectRule(fn () => $this->book($coach, ['activity_type_id' => $this->activity('Basketbol (Takım)')->id]), 'takım seçmelisiniz');

        $team = Team::where('coach_id', $coach->id)->first();
        $r = $this->book($coach, ['activity_type_id' => $this->activity('Basketbol (Takım)')->id, 'team_id' => $team->id, 'participant_count' => 12]);
        $this->assertSame('team', $r->kind);
        $this->assertCount(2, $r->units);
    }

    public function test_coach_can_only_book_for_own_team(): void
    {
        $other = Team::create(['name' => 'Başka Takım', 'coach_id' => $this->user('akademik')->id]);
        $this->expectRule(fn () => $this->book($this->user('antrenor'), ['activity_type_id' => $this->activity('Basketbol (Takım)')->id, 'team_id' => $other->id]), 'antrenörü olduğunuz takım');
    }

    public function test_daily_and_weekly_limits_per_facility(): void
    {
        $u = $this->user('ogrenci');
        $this->book($u);
        $this->expectRule(fn () => $this->book($u, ['start' => '14:00', 'end' => '15:00']), 'günlük 1');

        // Aynı gün başka tesis serbest
        $dance = Facility::where('name_tr', 'Dans Salonu')->first();
        $this->book($u, ['facility_id' => $dance->id, 'activity_type_id' => $dance->activities->first()->id]);

        $this->book($u, ['date' => '2026-11-26']);
        $this->book($u, ['date' => '2026-11-27']);
        $this->expectRule(fn () => $this->book($u, ['date' => '2026-11-28', 'start' => '11:00', 'end' => '12:00']), 'haftalık 3');
        // Sonraki hafta yeniden serbest
        $this->assertNotNull($this->book($u, ['date' => '2026-11-30']));
    }

    public function test_coach_is_exempt_from_limits_and_duration(): void
    {
        $coach = $this->user('antrenor');
        $this->book($coach, ['start' => '08:00', 'end' => '12:00']);
        $this->assertNotNull($this->book($coach, ['start' => '14:00', 'end' => '15:00']));
    }

    public function test_time_rules(): void
    {
        $u = $this->user('ogrenci');
        $this->expectRule(fn () => $this->book($u, ['start' => '10:00', 'end' => '13:00']), 'en fazla 2 saat');
        $this->expectRule(fn () => $this->book($u, ['date' => '2026-11-23', 'start' => '09:00', 'end' => '10:00']), 'saat önce');
        $this->expectRule(fn () => $this->book($u, ['date' => '2026-12-09']), 'gün sonrasına');
        $this->expectRule(fn () => $this->book($u, ['start' => '10:30', 'end' => '11:30']), 'slot');
        $this->expectRule(fn () => $this->book($u, ['start' => '21:00', 'end' => '23:00']), 'çalışma saatleri');
        $this->expectRule(fn () => $this->book($u, ['date' => '2026-11-29']), 'çalışma saatleri'); // Pazar kapalı
    }

    public function test_holiday_and_closure_block_booking(): void
    {
        Holiday::create(['date' => '2026-11-25', 'name_tr' => 'Test Tatili', 'name_en' => 'Test']);
        $this->expectRule(fn () => $this->book($this->user('ogrenci')), 'Resmi tatil');

        Closure::create(['facility_id' => $this->gym()->id, 'starts_at' => '2026-11-26 09:00', 'ends_at' => '2026-11-26 12:00', 'reason' => 'Zemin bakımı']);
        $this->expectRule(fn () => $this->book($this->user('ogrenci'), ['date' => '2026-11-26']), 'Zemin bakımı');
    }

    public function test_capacity_and_group_settings(): void
    {
        $study = Facility::where('name_tr', 'Çalışma Odası 1')->first();
        $act = $study->activities->first()->id;
        $this->expectRule(fn () => $this->book($this->user('ogrenci'), ['facility_id' => $study->id, 'activity_type_id' => $act, 'participant_count' => 7]), 'kapasitesini');
        $r = $this->book($this->user('ogrenci'), ['facility_id' => $study->id, 'activity_type_id' => $act, 'participant_count' => 4, 'start' => '10:00', 'end' => '10:30']);
        $this->assertSame('group', $r->kind);
        // Mezunlar çalışma odasını kullanamaz
        $this->expectRule(fn () => $this->book($this->user('mezun'), ['facility_id' => $study->id, 'activity_type_id' => $act, 'start' => '12:00', 'end' => '12:30']), 'yetkiniz yok');
    }

    public function test_participants_count_toward_their_own_limits(): void
    {
        $dance = Facility::where('name_tr', 'Dans Salonu')->first();
        $act = $dance->activities->first()->id;
        $p = $this->user('ogrenci2');
        $this->book($this->user('ogrenci'), ['facility_id' => $dance->id, 'activity_type_id' => $act, 'participants' => [$p->id]]);
        $this->expectRule(fn () => $this->book($p, ['facility_id' => $dance->id, 'activity_type_id' => $act, 'start' => '15:00', 'end' => '16:00']), 'günlük 1');
        $this->expectRule(fn () => $this->book($this->user('ogrenci3'), ['facility_id' => $dance->id, 'activity_type_id' => $act, 'start' => '17:00', 'end' => '18:00', 'participants' => [$p->id]]), $p->name);
    }

    public function test_equipment_stock_is_checked_per_time_range(): void
    {
        $ball = Equipment::where('name_tr', 'Basketbol topu')->first();
        $this->book($this->user('ogrenci'), ['equipment' => [$ball->id => 8]]);
        $this->expectRule(fn () => $this->book($this->user('ogrenci2'), ['equipment' => [$ball->id => 3]]), 'en fazla 2');
        $this->assertNotNull($this->book($this->user('ogrenci2'), ['equipment' => [$ball->id => 2]]));
    }

    public function test_cancelled_reservation_releases_the_slot(): void
    {
        $u = $this->user('ogrenci');
        $r = $this->book($u);
        $this->book($this->user('ogrenci2'));
        app(ReservationService::class)->cancelByOwner($u, $r);
        $this->assertNotNull($this->book($this->user('ogrenci3')));
    }

    public function test_sanctioned_user_cannot_book(): void
    {
        $u = $this->user('ogrenci');
        Sanction::create(['user_id' => $u->id, 'reason' => 'test', 'starts_at' => now(), 'ends_at' => now()->addDays(14)]);
        $this->expectRule(fn () => $this->book($u), 'rezervasyon yapamazsınız');
    }
}
