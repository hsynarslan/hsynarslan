<?php

namespace Tests\Feature;

use App\Mail\SystemMail;
use App\Models\Facility;
use App\Models\Reservation;
use App\Services\ReservationService;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Mail;
use Tests\TestCase;

class ApprovalFlowTest extends TestCase
{
    public function test_submission_notifies_facility_approvers_and_sks_admin(): void
    {
        $this->book($this->user('ogrenci'));
        Mail::assertQueued(SystemMail::class, fn ($m) => $m->hasTo($this->user('idari')->email) && $m->subjectKey === 'Yeni rezervasyon talebi');
        Mail::assertQueued(SystemMail::class, fn ($m) => $m->hasTo($this->user('sksadmin')->email));
    }

    public function test_approver_can_only_decide_for_assigned_facility(): void
    {
        $dance = Facility::where('name_tr', 'Dans Salonu')->first();
        $r = $this->book($this->user('ogrenci'), ['facility_id' => $dance->id, 'activity_type_id' => $dance->activities->first()->id]);

        $this->loginAs($this->user('idari'))->post(route('approvals.approve', $r))->assertForbidden();
        $this->assertSame(Reservation::PENDING, $r->fresh()->status);

        $this->loginAs($this->user('sksadmin'))->post(route('approvals.approve', $r))->assertRedirect();
        $this->assertSame(Reservation::APPROVED, $r->fresh()->status);
    }

    public function test_students_cannot_reach_approval_or_admin_pages(): void
    {
        $this->loginAs($this->user('ogrenci'));
        $this->get(route('approvals.index'))->assertForbidden();
        $this->get(route('admin.facilities.index'))->assertForbidden();
        $this->get(route('admin.audit.index'))->assertForbidden();
        $this->get(route('attendant.index'))->assertForbidden();
    }

    public function test_reject_requires_reason_and_frees_slot(): void
    {
        $r = $this->book($this->user('ogrenci'));
        $this->loginAs($this->user('idari'));
        $this->post(route('approvals.reject', $r), ['reason' => ''])->assertSessionHasErrors('reason');
        $this->post(route('approvals.reject', $r), ['reason' => 'Maç var'])->assertRedirect();
        $this->assertSame(Reservation::REJECTED, $r->fresh()->status);
        $this->assertFalse($r->reservationUnits()->first()->is_active);
        Mail::assertQueued(SystemMail::class, fn ($m) => $m->subjectKey === 'Rezervasyon talebiniz reddedildi');
    }

    public function test_pending_requests_get_reminder_then_expire_without_auto_approval(): void
    {
        $r = $this->book($this->user('ogrenci'));
        Carbon::setTestNow($this->now->copy()->addHours(5));
        $this->artisan('reservations:remind-approvers')->assertSuccessful();
        $this->assertNotNull($r->fresh()->approver_reminded_at);
        Mail::assertQueued(SystemMail::class, fn ($m) => $m->subjectKey === 'Hatırlatma: onay bekleyen talep');

        Carbon::setTestNow(Carbon::parse('2026-11-25 10:00'));
        $this->artisan('reservations:expire')->assertSuccessful();
        $this->assertSame(Reservation::EXPIRED, $r->fresh()->status);
        Mail::assertQueued(SystemMail::class, fn ($m) => $m->subjectKey === 'Talebiniz zamanında onaylanmadı');
    }

    public function test_full_http_flow_from_calendar_to_approval(): void
    {
        $this->loginAs($this->user('ogrenci'));
        $this->get(route('calendar', ['facility' => $this->gym()->id, 'date' => '2026-11-25']))->assertOk()->assertSee('Pota A');
        $this->post(route('reservations.store'), [
            'facility_id' => $this->gym()->id, 'activity_type_id' => $this->activity('Basketbol (Bireysel)')->id,
            'date' => '2026-11-25', 'start' => '10:00', 'end' => '11:00',
        ])->assertRedirect(route('reservations.index'))->assertSessionHas('success');
        $this->get(route('reservations.index'))->assertSee('YR-2026-');

        $this->loginAs($this->user('idari'))->get(route('approvals.index'))->assertOk()->assertSee('Elif Yılmaz');
    }

    public function test_late_cancellation_is_recorded(): void
    {
        $u = $this->user('ogrenci');
        $r = $this->book($u);
        app(ReservationService::class)->approve($this->user('idari'), $r);
        Carbon::setTestNow(Carbon::parse('2026-11-25 08:00')); // iptal süresi 3 saat
        $this->loginAs($u)->post(route('reservations.cancel', $r))->assertSessionHas('warning');
        $this->assertTrue($r->fresh()->is_late_cancel);
    }
}
