<?php

namespace App\Services;

use App\Mail\SystemMail;
use App\Models\Reservation;
use App\Models\Sanction;
use App\Models\Setting;
use App\Models\TeamPlayer;
use App\Models\User;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Mail;

/** E-posta bildirimleri (yalnızca e-posta kanalı). */
class Notifier
{
    public function send(?User $to, SystemMail $mail): void
    {
        if (! $to || ! $to->email || ! $to->is_active) {
            return;
        }
        Mail::to($to->email, $to->name)->locale($to->locale ?: 'tr')->queue($mail);
    }

    /** Tesisin onaycıları + SKS Admin'ler. */
    public function approversFor(Reservation $r): Collection
    {
        return $r->facility->approvers()->get()
            ->merge(User::where('is_sks_admin', true)->where('is_active', true)->get())
            ->unique('id');
    }

    public function submitted(Reservation $r): void
    {
        foreach ($this->approversFor($r) as $u) {
            $this->send($u, new SystemMail('Yeni rezervasyon talebi', [
                ['":name" yeni bir rezervasyon talebi oluşturdu.', ['name' => $r->user?->name]],
            ], $r->id, 'Onay bekleyenlere git', route('approvals.index')));
        }
    }

    public function approverReminder(Reservation $r): void
    {
        foreach ($this->approversFor($r) as $u) {
            $this->send($u, new SystemMail('Hatırlatma: onay bekleyen talep', [
                ['Aşağıdaki talep hâlâ onay bekliyor. Başlangıç saatine kadar onaylanmazsa otomatik iptal edilecek.'],
            ], $r->id, 'Onay bekleyenlere git', route('approvals.index')));
        }
    }

    public function approved(Reservation $r): void
    {
        $this->send($r->user, new SystemMail('Rezervasyonunuz onaylandı', [
            ['Rezervasyonunuz onaylandı. Tesise girişte QR kodunu okutmayı unutmayın.'],
        ], $r->id, 'Rezervasyonlarım', route('reservations.index')));
    }

    public function rejected(Reservation $r): void
    {
        $this->send($r->user, new SystemMail('Rezervasyon talebiniz reddedildi', [
            ['Rezervasyon talebiniz reddedildi.'],
            ['Gerekçe: :reason', ['reason' => $r->rejection_reason]],
        ], $r->id));
    }

    public function cancelledByStaff(Reservation $r): void
    {
        $this->send($r->user, new SystemMail('Rezervasyonunuz iptal edildi', [
            ['Rezervasyonunuz SKS tarafından iptal edildi.'],
            ['Gerekçe: :reason', ['reason' => $r->cancel_reason]],
        ], $r->id));
        foreach ($r->participantUsers as $p) {
            $this->send($p, new SystemMail('Katıldığınız rezervasyon iptal edildi', [
                ['Gerekçe: :reason', ['reason' => $r->cancel_reason]],
            ], $r->id));
        }
    }

    public function expired(Reservation $r): void
    {
        $this->send($r->user, new SystemMail('Talebiniz zamanında onaylanmadı', [
            ['Rezervasyon talebiniz başlangıç saatine kadar onaylanmadığı için otomatik olarak iptal edildi.'],
        ], $r->id));
    }

    public function participantAdded(Reservation $r, User $participant): void
    {
        $this->send($participant, new SystemMail('Bir rezervasyona katılımcı olarak eklendiniz', [
            ['":name" sizi bir rezervasyona katılımcı olarak ekledi. Bu rezervasyon sizin günlük ve haftalık limitinizden de düşer.', ['name' => $r->user?->name]],
            ['Katılmayacaksanız Rezervasyonlarım sayfasından listeden çıkabilirsiniz.'],
        ], $r->id, 'Rezervasyonlarım', route('reservations.index', ['tab' => 'participating'])));
    }

    public function reminder(Reservation $r, bool $dayBefore): void
    {
        $line = $dayBefore
            ? ['Yarın bir rezervasyonunuz var.']
            : ['Rezervasyonunuza 2 saat kaldı. Tesise girişte QR kodunu okutmayı unutmayın; ilk :min dakikada giriş yapılmazsa "gelmedi" sayılır.', ['min' => Setting::get('noshow_grace_minutes')]];
        $recipients = collect([$r->user])->merge($r->participantUsers)->filter()->unique('id');
        foreach ($recipients as $u) {
            $this->send($u, new SystemMail('Rezervasyon hatırlatması', [$line], $r->id));
        }
    }

    public function sanctioned(Sanction $s): void
    {
        $this->send($s->user, new SystemMail('Rezervasyon yasağı', [
            [':date tarihine kadar rezervasyon yapamazsınız.', ['date' => $s->ends_at->format('d.m.Y H:i')]],
            ['Gerekçe: :reason', ['reason' => $s->reason]],
        ]));
    }

    public function healthExpiring(TeamPlayer $p): void
    {
        $this->send($p->team->coach, new SystemMail('Sağlık raporu uyarısı', [
            [':player (:team) oyuncusunun sağlık raporu :date tarihinde sona eriyor / sona erdi.', [
                'player' => $p->user->name, 'team' => $p->team->name, 'date' => $p->healthExpiresOn()?->format('d.m.Y'),
            ]],
        ], null, 'Takımlarım', route('teams.show', $p->team_id)));
    }
}
