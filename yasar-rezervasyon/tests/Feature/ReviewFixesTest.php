<?php

namespace Tests\Feature;

use App\Models\Equipment;
use App\Models\Facility;
use App\Models\RecurringBlock;
use App\Models\Reservation;
use App\Models\ReservationEquipment;
use App\Models\Team;
use App\Models\User;
use App\Services\BlockService;
use App\Services\CheckinToken;
use App\Services\Ldap\Directory;
use App\Services\Ldap\DirectoryUser;
use App\Services\ReservationService;
use Illuminate\Support\Carbon;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

/** Harici kod incelemesinde (v1.0) bildirilen bulguların düzeltmelerini doğrular. */
class ReviewFixesTest extends TestCase
{
    private function svc(): ReservationService
    {
        return app(ReservationService::class);
    }

    private function approved(string $username, array $override = []): Reservation
    {
        $r = $this->book($this->user($username), $override);
        $this->svc()->approve($this->user('sksadmin'), $r);

        return $r->fresh();
    }

    private function assertRule(callable $fn, string $contains): void
    {
        try {
            $fn();
            $this->fail('ValidationException bekleniyordu: '.$contains);
        } catch (ValidationException $e) {
            $this->assertStringContainsString($contains, collect($e->errors())->flatten()->join(' '));
        }
    }

    // Bulgu 1 — Takım adına rezervasyon
    public function test_only_the_teams_coach_or_sks_admin_can_book_for_a_team(): void
    {
        $team = Team::where('coach_id', $this->user('antrenor')->id)->first();
        $teamActivity = ['activity_type_id' => $this->activity('Basketbol (Takım)')->id, 'team_id' => $team->id, 'participant_count' => 10];

        // Personel (antrenör değil) takım rezervasyonu yapamaz.
        $this->assertRule(fn () => $this->book($this->user('akademik'), $teamActivity), 'antrenörü olduğunuz takım');
        // Başka takımın antrenörü yapamaz.
        $other = $this->user('idari');
        $other->is_coach = true;
        $other->save();
        $this->assertRule(fn () => $this->book($other->fresh(), $teamActivity), 'antrenörü olduğunuz takım');
        // Takımın antrenörü ve SKS Admin yapabilir.
        $this->assertNotNull($this->book($this->user('antrenor'), $teamActivity));
        $this->assertNotNull($this->book($this->user('sksadmin'), $teamActivity + ['date' => '2026-11-26']));
    }

    public function test_team_activities_are_hidden_from_users_without_a_team(): void
    {
        $this->loginAs($this->user('akademik'))->get(route('reservations.create', ['facility' => $this->gym()->id, 'date' => '2026-11-25']))
            ->assertOk()->assertDontSee('Basketbol (Takım)')->assertSee('Voleybol');
    }

    // Bulgu 2 — QR
    public function test_dynamic_qr_link_expires_and_cannot_be_forged(): void
    {
        $this->approved('ogrenci');
        $gym = $this->gym();
        Carbon::setTestNow(Carbon::parse('2026-11-25 09:30'));
        $oldUrl = CheckinToken::url($gym);          // 09:30'da çekilen fotoğraf
        $this->loginAs($this->user('ogrenci'));

        Carbon::setTestNow(Carbon::parse('2026-11-25 10:00'));
        $this->post($oldUrl)->assertOk()->assertSee('süresi dolmuş');
        $this->assertNull(Reservation::first()->attendance);

        $w = CheckinToken::window();
        $this->post(route('checkin.dynamic.store', [$gym->id, $w, str_repeat('a', 32)]))->assertOk()->assertSee('süresi dolmuş');
        $this->post(route('checkin.store', $gym->checkin_token))->assertOk()->assertSee('süresi dolmuş'); // sabit bağlantı kapalı

        $this->post(CheckinToken::url($gym))->assertOk()->assertSee('Giriş kaydedildi');
        $this->assertSame('attended', Reservation::first()->attendance);
    }

    public function test_dynamic_qr_tolerates_the_confirmation_step_but_not_more(): void
    {
        $this->approved('ogrenci');
        $gym = $this->gym();
        Carbon::setTestNow(Carbon::parse('2026-11-25 09:58:00'));
        $url = CheckinToken::url($gym);
        Carbon::setTestNow(Carbon::parse('2026-11-25 09:58:50')); // okutup onaylamak ~50 sn sürdü
        $this->loginAs($this->user('ogrenci'))->post($url)->assertSee('Giriş kaydedildi');
    }

    public function test_static_mode_still_works_when_admin_chooses_it(): void
    {
        $this->approved('ogrenci');
        $gym = $this->gym();
        $gym->checkin_mode = 'static';
        $gym->save();
        Carbon::setTestNow(Carbon::parse('2026-11-25 10:00'));
        $this->loginAs($this->user('ogrenci'))->post(route('checkin.store', $gym->checkin_token))->assertSee('Giriş kaydedildi');
    }

    public function test_kiosk_is_limited_to_assigned_attendants(): void
    {
        $gym = $this->gym();
        $study = Facility::where('name_tr', 'Çalışma Odası 1')->first();
        $this->loginAs($this->user('gorevli'));
        $this->get(route('kiosk.show', $gym))->assertOk();
        $this->get(route('kiosk.qr', $gym))->assertOk()->assertHeader('Content-Type', 'image/svg+xml');
        $this->get(route('kiosk.show', $study))->assertForbidden();
        $this->loginAs($this->user('ogrenci'))->get(route('kiosk.qr', $gym))->assertForbidden();
    }

    // Bulgu 5 — Görevli tesis kapsamı
    public function test_attendant_can_only_act_on_assigned_facilities(): void
    {
        $study = Facility::where('name_tr', 'Çalışma Odası 1')->first();
        $r = $this->approved('ogrenci', ['facility_id' => $study->id, 'activity_type_id' => $study->activities->first()->id, 'start' => '10:00', 'end' => '10:30']);
        Carbon::setTestNow(Carbon::parse('2026-11-25 10:20'));

        $this->loginAs($this->user('gorevli'));
        $this->post(route('attendant.attendance', $r), ['attendance' => 'no_show'])->assertForbidden();
        $this->get(route('attendant.index', ['facility' => $study->id]))->assertOk()->assertDontSee('Çalışma Odası 1');
        $this->assertNull($r->fresh()->attendance);

        $this->loginAs($this->user('sksadmin'))->post(route('attendant.attendance', $r), ['attendance' => 'attended'])->assertRedirect();
    }

    public function test_revoked_role_takes_effect_in_the_open_session(): void
    {
        $g = $this->user('gorevli');
        $this->loginAs($g)->get(route('attendant.index'))->assertOk();
        $g->is_attendant = false;
        $g->save();
        $this->get(route('attendant.index'))->assertForbidden();
    }

    // Bulgu 6 — Katılımcı ayrılma
    public function test_leaving_is_idempotent_and_count_is_recomputed(): void
    {
        $dance = Facility::where('name_tr', 'Dans Salonu')->first();
        $p1 = $this->user('ogrenci2');
        $p2 = $this->user('ogrenci3');
        $r = $this->book($this->user('ogrenci'), ['facility_id' => $dance->id, 'activity_type_id' => $dance->activities->first()->id, 'participants' => [$p1->id, $p2->id]]);
        $this->assertSame(3, $r->participant_count);

        $this->loginAs($p1)->post(route('reservations.leave', $r))->assertRedirect();
        $this->post(route('reservations.leave', $r))->assertForbidden(); // ikinci kez
        $this->assertSame(2, $r->fresh()->participant_count);

        $this->svc()->cancelByOwner($this->user('ogrenci'), $r);
        $this->loginAs($p2)->post(route('reservations.leave', $r))->assertSessionHasErrors('reservation');
        $this->assertSame(2, $r->fresh()->participant_count);
    }

    // Ekipman durum makinesi
    public function test_equipment_state_transitions_are_enforced(): void
    {
        $ball = Equipment::where('name_tr', 'Basketbol topu')->first();
        $pending = $this->book($this->user('ogrenci2'), ['equipment' => [$ball->id => 1], 'start' => '12:00', 'end' => '13:00']);
        $r = $this->approved('ogrenci', ['equipment' => [$ball->id => 2]]);
        $item = ReservationEquipment::where('reservation_id', $r->id)->first();
        $g = $this->user('gorevli');

        $this->assertRule(fn () => $this->svc()->equipmentAction($g, $item, 'deliver'), 'rezervasyon günü'); // erken
        Carbon::setTestNow(Carbon::parse('2026-11-25 09:50'));
        $this->assertRule(fn () => $this->svc()->equipmentAction($g, $item, 'return', 'ok'), 'Teslim edilmemiş');
        $this->svc()->equipmentAction($g, $item, 'deliver');
        $this->assertRule(fn () => $this->svc()->equipmentAction($g, $item->fresh(), 'deliver'), 'zaten teslim');
        $this->svc()->equipmentAction($g, $item->fresh(), 'return', 'damaged', 'Supabı bozuk');
        $this->assertRule(fn () => $this->svc()->equipmentAction($g, $item->fresh(), 'return', 'ok'), 'zaten alındı');
        $this->assertSame('damaged', $item->fresh()->return_condition);

        $pendingItem = ReservationEquipment::where('reservation_id', $pending->id)->first();
        $this->assertRule(fn () => $this->svc()->equipmentAction($g, $pendingItem, 'deliver'), 'onaylı');
    }

    // Bulgu 4 — Sabit bloklar
    public function test_new_block_cannot_silently_cancel_an_existing_block(): void
    {
        $gym = $this->gym();
        $blocks = app(BlockService::class);
        $admin = $this->user('sksadmin');
        $data = fn ($title, $start, $end) => ['facility_id' => $gym->id, 'activity_type_id' => $this->activity('Voleybol')->id, 'team_id' => null,
            'title' => $title, 'weekdays' => [3], 'unit_ids' => $gym->units->pluck('id')->all(), 'start_time' => $start, 'end_time' => $end,
            'starts_on' => '2026-11-24', 'ends_on' => '2026-12-31'];
        $first = $blocks->create($admin, $data('Kadın Voleybol', '18:00', '20:00'), 'x');

        $this->assertRule(fn () => $blocks->create($admin, $data('Erkek Basketbol', '19:00', '21:00'), 'x'), 'Kadın Voleybol');
        $this->assertSame(0, $first->reservations()->where('status', 'cancelled')->count());
        $this->assertSame(1, RecurringBlock::count());
    }

    // Eş zamanlı durum geçişi: zamanlayıcı eski listeyle çalışırken talep onaylanmış olabilir.
    public function test_expire_does_not_override_a_reservation_approved_in_the_meantime(): void
    {
        $r = $this->book($this->user('ogrenci'));
        $stale = Reservation::find($r->id);              // zamanlayıcının elindeki eski kopya
        $this->svc()->approve($this->user('idari'), $r); // bu arada onaylandı
        Carbon::setTestNow(Carbon::parse('2026-11-25 10:00'));
        $this->svc()->expire($stale);
        $this->assertSame(Reservation::APPROVED, $r->fresh()->status);
        $this->assertTrue($r->reservationUnits()->first()->is_active);
    }

    public function test_cannot_approve_after_owner_cancelled_with_stale_copy(): void
    {
        $r = $this->book($this->user('ogrenci'));
        $stale = Reservation::find($r->id);
        $this->svc()->cancelByOwner($this->user('ogrenci'), $r);
        $this->assertRule(fn () => $this->svc()->approve($this->user('idari'), $stale), 'beklemede değil');
        $this->assertSame(Reservation::CANCELLED, $r->fresh()->status);
    }

    // İnceleme sırasında bulunan ek hata: hiç giriş yapmamış kişiyi katılımcı eklemek 500 veriyordu.
    public function test_participant_imported_from_ldap_during_the_request_works(): void
    {
        app(Directory::class)->add(['username' => 'yeni', 'name' => 'Yeni Öğrenci', 'email' => 'yeni@stu.yasar.edu.tr', 'number' => '20249999', 'role' => 'student']);
        $dance = Facility::where('name_tr', 'Dans Salonu')->first();
        $this->loginAs($this->user('ogrenci'))->post(route('reservations.store'), [
            'facility_id' => $dance->id, 'activity_type_id' => $dance->activities->first()->id,
            'date' => '2026-11-25', 'start' => '10:00', 'end' => '11:00', 'participant_numbers' => '20249999',
        ])->assertRedirect(route('reservations.index'))->assertSessionHas('success');
        $this->assertTrue(User::where('number', '20249999')->first()->participations()->exists());
    }

    public function test_changing_hours_warns_about_reservations_outside_new_hours(): void
    {
        $r = $this->book($this->user('ogrenci'), ['start' => '20:00', 'end' => '21:00']);
        $hours = collect(range(1, 7))->mapWithKeys(fn ($d) => [$d => ['opens_at' => '08:00', 'closes_at' => '18:00']])->all();
        $this->loginAs($this->user('sksadmin'))->put(route('admin.facilities.hours', $this->gym()), ['hours' => $hours])
            ->assertSessionHas('warning', fn ($m) => str_contains($m, $r->code));
    }

    public function test_ldap_outage_shows_a_clear_message_and_does_not_log_in(): void
    {
        $this->app->instance(Directory::class, new class implements Directory
        {
            public function authenticate(string $u, string $p): ?DirectoryUser
            {
                throw new \RuntimeException('LDAP down');
            }

            public function find(string $n): ?DirectoryUser
            {
                throw new \RuntimeException('LDAP down');
            }
        });
        $this->post('/login', ['username' => 'ogrenci', 'password' => 'test'])
            ->assertSessionHasErrors(['username' => __('Kimlik doğrulama servisine şu anda ulaşılamıyor. Lütfen daha sonra tekrar deneyin.')]);
        $this->assertGuest();
    }
}
