<?php

namespace App\Services;

use App\Models\Reservation;
use App\Models\ReservationParticipant;
use App\Models\Setting;
use App\Models\TeamPlayer;

/** Zamanlanmış görevler. Her biri birden fazla kez çalışsa da aynı sonucu üretir (idempotent). */
class ScheduledTasks
{
    public function __construct(
        private readonly ReservationService $reservations,
        private readonly SanctionService $sanctions,
        private readonly Notifier $notifier,
    ) {}

    /** Onay bekleyen talepler için onaycılara hatırlatma. */
    public function remindApprovers(): int
    {
        $after = Setting::get('approver_reminder_after_hours');
        $before = Setting::get('approver_reminder_before_start_hours');
        $list = Reservation::with('facility', 'user')->where('status', Reservation::PENDING)
            ->whereNull('approver_reminded_at')->where('starts_at', '>', now())
            ->where(fn ($q) => $q->where('created_at', '<=', now()->subHours($after))->orWhere('starts_at', '<=', now()->addHours($before)))
            ->get();
        foreach ($list as $r) {
            $r->forceFill(['approver_reminded_at' => now()])->save();
            $this->notifier->approverReminder($r);
        }

        return $list->count();
    }

    /** Başlangıç saatine kadar onaylanmayan talepleri otomatik iptal eder. */
    public function expirePending(): int
    {
        $list = Reservation::where('status', Reservation::PENDING)->where('starts_at', '<=', now())->get();
        foreach ($list as $r) {
            $this->reservations->expire($r);
        }

        return $list->count();
    }

    /** Kullanıcılara 1 gün ve 2 saat önce hatırlatma. */
    public function remindUsers(): int
    {
        $n = 0;
        $day = Reservation::with('participantUsers', 'user')->where('status', Reservation::APPROVED)->where('kind', '!=', 'block')
            ->whereNull('reminded_day_at')->where('starts_at', '>', now()->addHours(3))->where('starts_at', '<=', now()->addDay())->get();
        foreach ($day as $r) {
            $r->forceFill(['reminded_day_at' => now()])->save();
            $this->notifier->reminder($r, true);
            $n++;
        }
        $hours = Reservation::with('participantUsers', 'user')->where('status', Reservation::APPROVED)->where('kind', '!=', 'block')
            ->whereNull('reminded_hours_at')->where('starts_at', '>', now())->where('starts_at', '<=', now()->addHours(2))->get();
        foreach ($hours as $r) {
            $r->forceFill(['reminded_hours_at' => now()])->save();
            $this->notifier->reminder($r, false);
            $n++;
        }

        return $n;
    }

    /** İlk N dakikada check-in yapılmayan onaylı rezervasyonları "gelmedi" işaretler. */
    public function markNoShows(): int
    {
        $grace = Setting::get('noshow_grace_minutes');
        $list = Reservation::with('user')->withCount('checkins')->where('status', Reservation::APPROVED)
            ->where('kind', '!=', 'block')->whereNull('attendance')
            ->where('starts_at', '<=', now()->subMinutes($grace))->get();
        $n = 0;
        foreach ($list as $r) {
            if ($r->checkins_count > 0) {
                $r->forceFill(['attendance' => 'attended'])->save();

                continue;
            }
            if ($r->user?->isExemptFromLimits()) {
                continue; // Antrenörler gelmeme yaptırımından muaftır.
            }
            $r->forceFill(['attendance' => 'no_show'])->save();
            Audit::log('reservation.no_show', $r, [], null);
            if ($r->user) {
                $this->sanctions->evaluate($r->user);
            }
            $n++;
        }

        return $n;
    }

    /** Sağlık raporu bitişi yaklaşan / geçen oyuncular için antrenöre uyarı. */
    public function healthWarnings(): int
    {
        $days = Setting::get('health_warning_days');
        $n = 0;
        TeamPlayer::with('team.coach', 'user')->whereNull('left_on')->whereNull('health_warned_at')
            ->whereNotNull('health_report_expires_on')->lazyById(200)
            ->each(function (TeamPlayer $p) use ($days, &$n) {
                $exp = $p->healthExpiresOn();
                if ($exp && $exp->lte(today()->addDays($days))) {
                    $p->forceFill(['health_warned_at' => now()])->save();
                    $this->notifier->healthExpiring($p);
                    $n++;
                }
            });

        return $n;
    }

    /** Saklama süresi dolan rezervasyonları anonimleştirir (ISO 27001 A.8.10, KVKK md. 7). */
    public function purgeExpiredData(): int
    {
        $cutoff = now()->subYears(Setting::get('retention_years'));
        $n = 0;
        Reservation::whereNull('anonymized_at')->where('ends_at', '<', $cutoff)->lazyById(500)->each(function (Reservation $r) use (&$n) {
            ReservationParticipant::where('reservation_id', $r->id)->delete();
            $r->checkins()->delete();
            $r->forceFill(['user_id' => null, 'notes' => null, 'rejection_reason' => null, 'cancel_reason' => null,
                'decided_by' => null, 'cancelled_by' => null, 'attendance_marked_by' => null, 'anonymized_at' => now()])->save();
            $n++;
        });
        if ($n) {
            Audit::log('retention.anonymized', null, ['count' => $n, 'cutoff' => (string) $cutoff], null);
        }

        return $n;
    }
}
