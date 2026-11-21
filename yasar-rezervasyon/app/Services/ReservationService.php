<?php

namespace App\Services;

use App\Models\ActivityType;
use App\Models\Equipment;
use App\Models\Facility;
use App\Models\FacilityUnit;
use App\Models\Reservation;
use App\Models\ReservationCheckin;
use App\Models\ReservationEquipment;
use App\Models\Setting;
use App\Models\Team;
use App\Models\User;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * Rezervasyon iş kuralları. Kontrol sırası gereksinim dokümanı bölüm 5.2 ile aynıdır.
 */
class ReservationService
{
    public function __construct(
        private readonly RuleResolver $rules,
        private readonly Availability $availability,
        private readonly SanctionService $sanctions,
        private readonly Notifier $notifier,
    ) {}

    /**
     * @param array{facility_id:int, activity_type_id:int, date:string, start:string, end:string, unit_id?:?int,
     *              team_id?:?int, participants?:array<int>, participant_count?:?int, equipment?:array<int,int>, notes?:?string} $input
     */
    public function create(User $user, array $input): Reservation
    {
        $facility = Facility::with('units', 'hours', 'rules', 'equipment')->findOrFail($input['facility_id']);
        $activity = ActivityType::where('facility_id', $facility->id)->find($input['activity_type_id']);
        $start = Carbon::parse($input['date'].' '.$input['start']);
        $end = Carbon::parse($input['date'].' '.$input['end']);
        if ($input['end'] === '00:00') {
            $end->addDay();
        }

        $fail = fn (string $field, string $message, array $replace = []) => throw ValidationException::withMessages([$field => __($message, $replace)]);

        // 1. Yaptırım
        if (! $user->isExemptFromLimits() && ($s = $user->activeSanction())) {
            $fail('facility_id', ':date tarihine kadar rezervasyon yapamazsınız.', ['date' => $s->ends_at->format('d.m.Y H:i')]);
        }

        // 2. Tesis ve aktivite yetkisi
        if (! $facility->is_active || ! $facility->allowsAnyRole($user->bookingRoles())) {
            $fail('facility_id', 'Bu tesisi rezerve etme yetkiniz yok.');
        }
        if (! $activity || ! $activity->is_active) {
            $fail('activity_type_id', 'Geçerli bir aktivite seçin.');
        }
        if (! $activity->allowsAnyRole($user->bookingRoles())) {
            $fail('activity_type_id', 'Bu aktiviteyi rezerve etme yetkiniz yok.');
        }

        // 3. Takım
        $team = null;
        if ($activity->is_team) {
            $team = Team::where('is_active', true)->find($input['team_id'] ?? null);
            if (! $team) {
                $fail('team_id', 'Takım aktivitesi için takım seçmelisiniz.');
            }
            // Takım adına yalnızca takımın antrenörü veya SKS Admin rezervasyon yapabilir.
            if (! $user->canBookForTeam($team)) {
                $fail('team_id', 'Yalnızca antrenörü olduğunuz takım için rezervasyon yapabilirsiniz.');
            }
        }

        // 4–6. Zaman, çalışma saatleri, tatil ve kapatmalar
        if ($end->lte($start)) {
            $fail('end', 'Bitiş saati başlangıçtan sonra olmalıdır.');
        }
        if (! $this->availability->isAligned($facility, $start, $end)) {
            $fail('start', 'Saatler :min dakikalık slotlara uymalıdır.', ['min' => $facility->slot_minutes]);
        }
        if (! $this->availability->withinOpeningHours($facility, $start, $end)) {
            $fail('start', 'Seçilen saatler tesisin çalışma saatleri dışında.');
        }
        if ($h = $this->availability->holidayConflict($start, $end)) {
            $fail('date', 'Resmi tatil: :name', ['name' => $h->name]);
        }
        if ($c = $this->availability->closureConflict($facility, $start, $end)) {
            $fail('date', 'Tesis bu saatlerde kapalı: :reason', ['reason' => $c->reason]);
        }

        // 7–8. Kural değerleri
        $rule = $this->rules->for($facility, $user);
        if ($start->lt(now()->addHours($rule['min_hours_before']))) {
            $fail('start', 'Rezervasyon en geç başlangıçtan :h saat önce yapılabilir.', ['h' => $rule['min_hours_before']]);
        }
        if ($start->gt(now()->addDays($rule['max_days_ahead'])->endOfDay())) {
            $fail('date', 'En fazla :d gün sonrasına rezervasyon yapılabilir.', ['d' => $rule['max_days_ahead']]);
        }
        if (! $user->isExemptFromLimits() && $start->diffInMinutes($end) > $rule['max_duration_minutes']) {
            $fail('end', 'Bir rezervasyon en fazla :h saat sürebilir.', ['h' => round($rule['max_duration_minutes'] / 60, 1)]);
        }

        // 9–10. Kapasite ve katılımcılar
        $participants = User::whereIn('id', array_unique($input['participants'] ?? []))->where('id', '!=', $user->id)->get();
        if ($facility->participant_list_required) {
            $count = 1 + $participants->count();
        } else {
            $count = max(1, (int) ($input['participant_count'] ?? 1));
            $participants = collect();
        }
        if ($team) {
            $count = max($count, (int) ($input['participant_count'] ?? 1));
        }
        if (! $facility->group_allowed && ! $team && $count > 1) {
            $fail('participant_count', 'Bu tesis grup rezervasyonuna kapalıdır.');
        }
        if ($count > $facility->capacity) {
            $fail('participant_count', 'Kişi sayısı tesis kapasitesini (:c) aşıyor.', ['c' => $facility->capacity]);
        }
        foreach ($participants as $p) {
            if (! $p->is_active || ! $facility->allowsAnyRole($p->bookingRoles())) {
                $fail('participants', ':name bu tesisi kullanma yetkisine sahip değil.', ['name' => $p->name]);
            }
        }

        // 11. Limitlerin ön kontrolü (kullanıcıya hızlı geri bildirim; kesin kontrol aşağıda kilit altında).
        $people = collect([$user])->merge($participants);
        foreach ($people as $person) {
            $this->checkLimits($person, $facility, $start, $person->is($user) ? null : $person->name);
        }

        return DB::transaction(function () use ($user, $facility, $activity, $team, $start, $end, $count, $participants, $people, $input, $fail) {
            // Eş zamanlı isteklere karşı kilitler. Kilitlenme (deadlock) olmaması için sıra her zaman aynıdır:
            // önce kullanıcılar (id sırasıyla), sonra tesis birimleri, sonra ekipman.
            User::whereIn('id', $people->pluck('id')->sort()->values())->orderBy('id')->lockForUpdate()->get();
            FacilityUnit::where('facility_id', $facility->id)->orderBy('id')->lockForUpdate()->get();

            // 11. Günlük ve haftalık limit — kilit altında yeniden (aynı kullanıcının paralel istekleri burada sıralanır).
            foreach ($people as $person) {
                $this->checkLimits($person, $facility, $start, $person->is($user) ? null : $person->name);
            }

            // 12. Birim uygunluğu
            $free = $this->availability->freeUnits($facility, $start, $end);
            $preferred = $input['unit_id'] ?? null;
            if ($preferred) {
                $free = $free->sortBy(fn ($u) => $u->id === (int) $preferred ? 0 : 1)->values();
            }
            if ($free->count() < $activity->units_required) {
                $fail('start', 'Seçilen saatte yeterli boş alan yok. Lütfen takvimden başka bir saat seçin.');
            }
            $units = $free->take($activity->units_required);

            // 13. Ekipman stoku (ekipman birden fazla tesiste kullanılabildiği için ayrıca kilitlenir)
            $equipmentIds = array_map('intval', array_keys(array_filter($input['equipment'] ?? [], fn ($q) => (int) $q > 0)));
            if ($equipmentIds) {
                Equipment::whereIn('id', $equipmentIds)->orderBy('id')->lockForUpdate()->get();
            }
            $equipment = $this->resolveEquipment($facility, $input['equipment'] ?? [], $start, $end, $fail);

            $kind = $team ? 'team' : ($count > 1 ? 'group' : 'individual');
            $reservation = Reservation::create([
                'code' => 'TMP-'.bin2hex(random_bytes(6)),
                'facility_id' => $facility->id,
                'activity_type_id' => $activity->id,
                'user_id' => $user->id,
                'team_id' => $team?->id,
                'kind' => $kind,
                'status' => Reservation::PENDING,
                'starts_at' => $start,
                'ends_at' => $end,
                'participant_count' => $count,
                'notes' => $input['notes'] ?? null,
            ]);
            $reservation->code = sprintf('YR-%s-%06d', $start->year, $reservation->id);
            $reservation->save();

            $this->attachUnits($reservation, $units);
            foreach ($participants as $p) {
                $reservation->participants()->create(['user_id' => $p->id]);
            }
            foreach ($equipment as $id => $qty) {
                $reservation->equipmentItems()->create(['equipment_id' => $id, 'quantity' => $qty]);
            }

            Audit::log('reservation.created', $reservation, [
                'facility' => $facility->id, 'activity' => $activity->id, 'starts_at' => (string) $start,
                'ends_at' => (string) $end, 'units' => $units->pluck('id')->all(), 'participants' => $participants->pluck('id')->all(),
                'equipment' => $equipment, 'team' => $team?->id,
            ]);

            DB::afterCommit(function () use ($reservation, $participants) {
                $reservation->load('facility', 'user');
                $this->notifier->submitted($reservation);
                foreach ($participants as $p) {
                    $this->notifier->participantAdded($reservation, $p);
                }
            });

            return $reservation;
        });
    }

    private function checkLimits(User $person, Facility $facility, Carbon $start, ?string $participantName): void
    {
        if ($person->isExemptFromLimits()) {
            return;
        }
        $rule = $this->rules->for($facility, $person);
        $base = Reservation::active()->where('facility_id', $facility->id)->where('kind', '!=', 'block')->involving($person->id);

        $who = $participantName ? ['field' => 'participants', 'prefix' => $participantName.': '] : ['field' => 'start', 'prefix' => ''];

        $day = (clone $base)->whereBetween('starts_at', [$start->copy()->startOfDay(), $start->copy()->endOfDay()])->count();
        if ($day >= $rule['max_per_day']) {
            throw ValidationException::withMessages([$who['field'] => $who['prefix'].__('Bu tesis için günlük :n rezervasyon sınırına ulaşıldı.', ['n' => $rule['max_per_day']])]);
        }
        $week = (clone $base)->whereBetween('starts_at', [$start->copy()->startOfWeek(), $start->copy()->endOfWeek()])->count();
        if ($week >= $rule['max_per_week']) {
            throw ValidationException::withMessages([$who['field'] => $who['prefix'].__('Bu tesis için haftalık :n rezervasyon sınırına ulaşıldı.', ['n' => $rule['max_per_week']])]);
        }
    }

    /** @return array<int,int> */
    private function resolveEquipment(Facility $facility, array $requested, Carbon $start, Carbon $end, callable $fail): array
    {
        $result = [];
        foreach ($requested as $id => $qty) {
            $qty = (int) $qty;
            if ($qty <= 0) {
                continue;
            }
            $eq = $facility->equipment->firstWhere('id', (int) $id);
            if (! $eq || ! $eq->is_active) {
                $fail('equipment', 'Geçersiz ekipman seçimi.');
            }
            $remaining = $this->availability->equipmentRemaining($eq, $start, $end);
            if ($qty > $remaining) {
                $fail('equipment', ':name için bu saatte en fazla :n adet kaldı.', ['name' => $eq->name, 'n' => $remaining]);
            }
            $result[$eq->id] = $qty;
        }

        return $result;
    }

    public function attachUnits(Reservation $reservation, Collection $units): void
    {
        foreach ($units as $unit) {
            $reservation->reservationUnits()->create([
                'facility_unit_id' => $unit->id,
                'starts_at' => $reservation->starts_at,
                'ends_at' => $reservation->ends_at,
                'is_active' => $reservation->isActive(),
            ]);
        }
    }

    /**
     * Durum değiştiren her işlem rezervasyon satırını kilitleyip güncel halini okur. Böylece aynı anda gelen
     * onay, red, iptal ve otomatik sonlandırma birbirinin üzerine yazamaz; ikinci işlem güncel durumu görür.
     */
    private function locked(Reservation $r, callable $fn): Reservation
    {
        return DB::transaction(function () use ($r, $fn) {
            $fresh = Reservation::whereKey($r->getKey())->lockForUpdate()->firstOrFail();
            $fn($fresh);
            $r->setRawAttributes($fresh->getAttributes(), true);

            return $fresh;
        });
    }

    public function approve(User $approver, Reservation $r): void
    {
        $r = $this->locked($r, function (Reservation $r) use ($approver) {
            $this->assertCanDecide($approver, $r);
            $r->status = Reservation::APPROVED;
            $r->decided_by = $approver->id;
            $r->decided_at = now();
            $r->save();
            Audit::log('reservation.approved', $r);
        });
        $this->notifier->approved($r);
    }

    public function reject(User $approver, Reservation $r, string $reason): void
    {
        $r = $this->locked($r, function (Reservation $r) use ($approver, $reason) {
            $this->assertCanDecide($approver, $r);
            $r->status = Reservation::REJECTED;
            $r->decided_by = $approver->id;
            $r->decided_at = now();
            $r->rejection_reason = $reason;
            $r->save();
            $r->syncUnitLocks();
            Audit::log('reservation.rejected', $r, ['reason' => $reason]);
        });
        $this->notifier->rejected($r);
    }

    private function assertCanDecide(User $approver, Reservation $r): void
    {
        if (! $approver->canApproveFacility($r->facility_id)) {
            abort(403);
        }
        if ($r->status !== Reservation::PENDING) {
            throw ValidationException::withMessages(['reservation' => __('Bu talep artık beklemede değil.')]);
        }
        if ($r->starts_at->isPast()) {
            throw ValidationException::withMessages(['reservation' => __('Başlangıç saati geçmiş bir talep onaylanamaz.')]);
        }
    }

    /** Sahibinin iptali / geri çekmesi. */
    public function cancelByOwner(User $user, Reservation $r): void
    {
        $late = false;
        $this->locked($r, function (Reservation $r) use ($user, &$late) {
            if ($r->user_id !== $user->id || $r->isBlock()) {
                abort(403);
            }
            if (! $r->isActive()) {
                throw ValidationException::withMessages(['reservation' => __('Bu rezervasyon iptal edilemez.')]);
            }
            if ($r->starts_at->lte(now())) {
                throw ValidationException::withMessages(['reservation' => __('Başlamış bir rezervasyon iptal edilemez.')]);
            }
            $late = $r->status === Reservation::APPROVED && now()->gt($r->cancelDeadline());
            $r->status = Reservation::CANCELLED;
            $r->cancelled_by = $user->id;
            $r->cancelled_at = now();
            $r->is_late_cancel = $late;
            $r->save();
            $r->syncUnitLocks();
            Audit::log($late ? 'reservation.cancelled_late' : 'reservation.cancelled', $r);
        });
        if ($late) {
            $this->sanctions->evaluate($user);
        }
    }

    /** SKS (onaycı veya admin) iptali. Geç iptal sayılmaz. */
    public function cancelByStaff(User $staff, Reservation $r, string $reason, bool $notify = true): void
    {
        if (! $staff->canApproveFacility($r->facility_id)) {
            abort(403);
        }
        $changed = false;
        $r = $this->locked($r, function (Reservation $r) use ($staff, $reason, &$changed) {
            if (! $r->isActive()) {
                return;
            }
            $r->status = Reservation::CANCELLED;
            $r->cancelled_by = $staff->id;
            $r->cancelled_at = now();
            $r->cancel_reason = $reason;
            $r->save();
            $r->syncUnitLocks();
            Audit::log('reservation.cancelled_by_staff', $r, ['reason' => $reason]);
            $changed = true;
        });
        if ($changed && $notify && ! $r->isBlock()) {
            $this->notifier->cancelledByStaff($r->load('participantUsers'));
        }
    }

    public function leave(User $user, Reservation $r): void
    {
        $this->locked($r, function (Reservation $r) use ($user) {
            $p = $r->participants()->where('user_id', $user->id)->lockForUpdate()->first();
            if (! $p) {
                abort(403); // Katılımcı değil ya da zaten ayrılmış.
            }
            if (! $r->isActive()) {
                throw ValidationException::withMessages(['reservation' => __('Bu rezervasyon artık aktif değil.')]);
            }
            if ($r->starts_at->lte(now())) {
                throw ValidationException::withMessages(['reservation' => __('Başlamış bir rezervasyondan çıkılamaz.')]);
            }
            $p->update(['removed_at' => now()]);
            // Sayı azaltılmaz, aktif katılımcılardan yeniden hesaplanır (sahip + kalan katılımcılar).
            $remaining = $r->participants()->count();
            $r->participant_count = $r->facility->participant_list_required
                ? 1 + $remaining
                : max(1, $r->participant_count - 1);
            $r->save();
            Audit::log('reservation.participant_left', $r, ['participant_count' => $r->participant_count]);
        });
    }

    /** Zamanlanmış görev: başlangıca kadar onaylanmayan talebi sonlandırır. Bu arada onaylandıysa dokunmaz. */
    public function expire(Reservation $r): void
    {
        $expired = false;
        $r = $this->locked($r, function (Reservation $r) use (&$expired) {
            if ($r->status !== Reservation::PENDING) {
                return;
            }
            $r->status = Reservation::EXPIRED;
            $r->save();
            $r->syncUnitLocks();
            Audit::log('reservation.expired', $r, [], null);
            $expired = true;
        });
        if ($expired) {
            $this->notifier->expired($r);
        }
    }

    /** QR ile giriş. Uygun rezervasyon yoksa null. */
    public function checkin(User $user, Facility $facility, string $method = 'qr'): ?Reservation
    {
        $early = Setting::get('checkin_early_minutes');
        $grace = Setting::get('noshow_grace_minutes');
        $r = Reservation::where('facility_id', $facility->id)
            ->where('status', Reservation::APPROVED)
            ->involving($user->id)
            ->where('starts_at', '<=', now()->addMinutes($early))
            ->where('starts_at', '>=', now()->subMinutes($grace))
            ->orderBy('starts_at')->first();
        if (! $r) {
            return null;
        }

        ReservationCheckin::firstOrCreate(
            ['reservation_id' => $r->id, 'user_id' => $user->id],
            ['method' => 'qr', 'checked_in_at' => now()],
        );
        if ($r->attendance !== 'attended') {
            $r->attendance = 'attended';
            $r->save();
        }
        Audit::log('reservation.checkin', $r, ['method' => $method]);

        return $r;
    }

    /** Görevli işaretlemesi / düzeltmesi. */
    public function markAttendance(User $staff, Reservation $r, string $attendance): void
    {
        if (! $staff->canAttendFacility($r->facility_id)) {
            abort(403);
        }
        $previous = null;
        $r = $this->locked($r, function (Reservation $r) use ($staff, $attendance, &$previous) {
            // Yalnızca onaylı, blok olmayan ve başlamasına en fazla 60 dk kalmış / başlamış rezervasyonlar.
            if ($r->status !== Reservation::APPROVED || $r->isBlock() || $r->starts_at->gt(now()->addHour())) {
                throw ValidationException::withMessages(['reservation' => __('Bu rezervasyon için katılım işaretlenemez.')]);
            }
            $previous = $r->attendance;
            $r->attendance = $attendance;
            $r->attendance_marked_by = $staff->id;
            $r->attendance_marked_at = now();
            $r->save();
            if ($attendance === 'attended' && $r->user_id) {
                ReservationCheckin::firstOrCreate(
                    ['reservation_id' => $r->id, 'user_id' => $r->user_id],
                    ['method' => 'attendant', 'checked_in_at' => now()],
                );
            }
            Audit::log('reservation.attendance_marked', $r, ['from' => $previous, 'to' => $attendance]);
        });
        if ($r->user && $previous !== $attendance) {
            $this->sanctions->evaluate($r->user, liftIfBelow: $attendance === 'attended');
        }
    }

    /** Ekipman teslim/iade (görevli). */
    /**
     * Ekipman teslim/iade (görevli). Geçerli durum geçişleri:
     *   bekliyor --teslim et--> teslim edildi --teslim al--> iade edildi (sağlam / hasarlı / kayıp)
     */
    public function equipmentAction(User $staff, ReservationEquipment $item, string $action, ?string $condition = null, ?string $note = null): void
    {
        $reservation = $item->reservation;
        if (! $staff->canAttendFacility($reservation->facility_id)) {
            abort(403);
        }
        DB::transaction(function () use ($staff, $item, $action, $condition, $note) {
            $item = ReservationEquipment::whereKey($item->id)->lockForUpdate()->firstOrFail();
            $r = Reservation::whereKey($item->reservation_id)->lockForUpdate()->firstOrFail();
            $fail = fn (string $m) => throw ValidationException::withMessages(['equipment' => __($m)]);

            if ($action === 'deliver') {
                if ($r->status !== Reservation::APPROVED) {
                    $fail('Ekipman yalnızca onaylı rezervasyonlara teslim edilebilir.');
                }
                if ($item->delivered_at) {
                    $fail('Bu ekipman zaten teslim edildi.');
                }
                if (! $r->starts_at->isSameDay(now()) && ! now()->between($r->starts_at, $r->ends_at)) {
                    $fail('Ekipman yalnızca rezervasyon günü teslim edilebilir.');
                }
                $item->delivered_at = now();
                $item->delivered_by = $staff->id;
            } else {
                if (! $item->delivered_at) {
                    $fail('Teslim edilmemiş ekipman iade alınamaz.');
                }
                if ($item->returned_at) {
                    $fail('Bu ekipmanın iadesi zaten alındı.');
                }
                $item->returned_at = now();
                $item->returned_by = $staff->id;
                $item->return_condition = $condition ?? 'ok';
                $item->note = $note;
            }
            $item->save();
            Audit::log('equipment.'.$action, $r, ['equipment' => $item->equipment_id, 'condition' => $condition]);
        });
    }
}
