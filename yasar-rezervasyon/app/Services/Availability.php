<?php

namespace App\Services;

use App\Models\Closure;
use App\Models\Equipment;
use App\Models\Facility;
use App\Models\FacilityHour;
use App\Models\Holiday;
use App\Models\ReservationEquipment;
use App\Models\ReservationUnit;
use Carbon\CarbonInterface;
use Illuminate\Support\Collection;

/** Tesis, birim ve ekipman uygunluğu. */
class Availability
{
    public function withinOpeningHours(Facility $facility, CarbonInterface $start, CarbonInterface $end): bool
    {
        if (! $start->isSameDay($end) && ! ($end->format('H:i') === '00:00' && $end->copy()->subDay()->isSameDay($start))) {
            return false;
        }
        $hour = $facility->hourFor($start->isoWeekday());
        if (! $hour || $hour->is_closed) {
            return false;
        }

        $s = $start->hour * 60 + $start->minute;
        $e = $start->isSameDay($end) ? $end->hour * 60 + $end->minute : 1440;
        $close = $hour->closesMinute() ?: 1440;

        return $s >= $hour->opensMinute() && $e <= $close;
    }

    public function isAligned(Facility $facility, CarbonInterface $start, CarbonInterface $end): bool
    {
        $hour = $facility->hourFor($start->isoWeekday());
        $offset = $hour ? $hour->opensMinute() : 0;
        $minutes = (int) $start->diffInMinutes($end);
        $s = $start->hour * 60 + $start->minute;

        return $minutes > 0 && $minutes % $facility->slot_minutes === 0 && ($s - $offset) % $facility->slot_minutes === 0;
    }

    public function holidayConflict(CarbonInterface $start, CarbonInterface $end): ?Holiday
    {
        $holiday = Holiday::whereDate('date', $start->toDateString())->first();
        if (! $holiday) {
            return null;
        }
        if (! $holiday->closed_from) {
            return $holiday;
        }

        $from = $start->copy()->setTimeFromTimeString($holiday->closed_from);

        return $end->gt($from) ? $holiday : null;
    }

    public function closureConflict(Facility $facility, CarbonInterface $start, CarbonInterface $end): ?Closure
    {
        return Closure::where(fn ($q) => $q->whereNull('facility_id')->orWhere('facility_id', $facility->id))
            ->where('starts_at', '<', $end)->where('ends_at', '>', $start)->first();
    }

    /** Verilen aralıkta dolu olan birim kimlikleri. */
    public function busyUnitIds(Facility $facility, CarbonInterface $start, CarbonInterface $end): array
    {
        return ReservationUnit::query()
            ->whereIn('facility_unit_id', $facility->units->pluck('id'))
            ->where('is_active', true)
            ->where('starts_at', '<', $end)->where('ends_at', '>', $start)
            ->pluck('facility_unit_id')->unique()->values()->all();
    }

    public function freeUnits(Facility $facility, CarbonInterface $start, CarbonInterface $end): Collection
    {
        $busy = $this->busyUnitIds($facility, $start, $end);

        return $facility->units->reject(fn ($u) => in_array($u->id, $busy, true))->values();
    }

    public function equipmentRemaining(Equipment $equipment, CarbonInterface $start, CarbonInterface $end): int
    {
        $used = ReservationEquipment::where('equipment_id', $equipment->id)
            ->whereHas('reservation', fn ($q) => $q->active()->overlapping($start, $end))
            ->sum('quantity');

        return max(0, $equipment->total_stock - (int) $used);
    }

    /** Takvimde bir günün slot başlangıçları (dakika cinsinden). */
    public function daySlots(Facility $facility, CarbonInterface $day): array
    {
        $hour = $facility->hourFor($day->isoWeekday());
        if (! $hour || $hour->is_closed) {
            return [];
        }
        $slots = [];
        $close = $hour->closesMinute() ?: 1440;
        for ($m = $hour->opensMinute(); $m + $facility->slot_minutes <= $close; $m += $facility->slot_minutes) {
            $slots[] = $m;
        }

        return $slots;
    }

    /** Takvim için bir günün tüm aktif rezervasyon birimleri. */
    public function dayLocks(Facility $facility, CarbonInterface $from, CarbonInterface $to): Collection
    {
        return ReservationUnit::query()
            ->with(['reservation' => fn ($q) => $q->with('user:id,name', 'activity', 'block', 'team')])
            ->whereIn('facility_unit_id', $facility->units->pluck('id'))
            ->where('is_active', true)
            ->where('starts_at', '<', $to)->where('ends_at', '>', $from)
            ->get();
    }

    public static function minutesToTime(int $m): string
    {
        return sprintf('%02d:%02d', intdiv($m, 60) % 24, $m % 60);
    }

    public static function timeToMinutes(string $t): int
    {
        return FacilityHour::toMinutes($t);
    }
}
