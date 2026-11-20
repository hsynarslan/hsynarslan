<?php

namespace App\Services;

use App\Models\Closure;
use App\Models\Facility;
use App\Models\RecurringBlock;
use App\Models\Reservation;
use App\Models\User;
use Carbon\CarbonPeriod;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

/** SKS sabit blokları ve kapatmaları. */
class BlockService
{
    public function __construct(
        private readonly Availability $availability,
        private readonly ReservationService $reservations,
    ) {}

    /**
     * Önizleme: oluşacak tarihler, atlanan günler ve çakışan rezervasyonlar.
     *
     * @return array{occurrences: array<int,array{start:Carbon,end:Carbon}>, skipped: array<int,array{date:string,reason:string}>, conflicts: Collection}
     */
    public function preview(array $data): array
    {
        $facility = Facility::with('units')->findOrFail($data['facility_id']);
        $occurrences = [];
        $skipped = [];
        $conflicts = collect();

        foreach (CarbonPeriod::create($data['starts_on'], $data['ends_on']) as $day) {
            if (! in_array($day->isoWeekday(), array_map('intval', $data['weekdays']), true)) {
                continue;
            }
            $start = Carbon::parse($day->toDateString().' '.$data['start_time']);
            $end = Carbon::parse($day->toDateString().' '.$data['end_time']);
            if ($h = $this->availability->holidayConflict($start, $end)) {
                $skipped[] = ['date' => $day->toDateString(), 'reason' => $h->name];

                continue;
            }
            if ($c = $this->availability->closureConflict($facility, $start, $end)) {
                $skipped[] = ['date' => $day->toDateString(), 'reason' => $c->reason];

                continue;
            }
            $occurrences[] = ['start' => $start, 'end' => $end];
            $conflicts = $conflicts->merge($this->conflicting($facility, $data['unit_ids'], $start, $end));
        }

        return ['occurrences' => $occurrences, 'skipped' => $skipped, 'conflicts' => $conflicts->unique('id')->values()];
    }

    public function conflicting(Facility $facility, array $unitIds, $start, $end): Collection
    {
        return Reservation::active()->with('user', 'facility')
            ->where('facility_id', $facility->id)
            ->overlapping($start, $end)
            ->whereHas('reservationUnits', fn ($q) => $q->whereIn('facility_unit_id', $unitIds))
            ->get();
    }

    public function create(User $admin, array $data, string $cancelReason): RecurringBlock
    {
        return DB::transaction(function () use ($admin, $data, $cancelReason) {
            $preview = $this->preview($data);
            foreach ($preview['conflicts'] as $r) {
                $this->reservations->cancelByStaff($admin, $r, $cancelReason);
            }
            $block = RecurringBlock::create($data + ['created_by' => $admin->id]);
            $units = $block->facility->units()->whereIn('id', $data['unit_ids'])->get();
            foreach ($preview['occurrences'] as $o) {
                $r = Reservation::create([
                    'code' => 'TMP-'.bin2hex(random_bytes(6)),
                    'facility_id' => $block->facility_id,
                    'activity_type_id' => $block->activity_type_id,
                    'user_id' => $admin->id,
                    'team_id' => $block->team_id,
                    'recurring_block_id' => $block->id,
                    'kind' => 'block',
                    'status' => Reservation::APPROVED,
                    'starts_at' => $o['start'],
                    'ends_at' => $o['end'],
                    'participant_count' => 0,
                ]);
                $r->code = sprintf('YR-%s-%06d', $o['start']->year, $r->id);
                $r->decided_by = $admin->id;
                $r->decided_at = now();
                $r->save();
                $this->reservations->attachUnits($r, $units);
            }
            Audit::log('block.created', $block, ['occurrences' => count($preview['occurrences']), 'cancelled' => $preview['conflicts']->pluck('id')->all()]);

            return $block;
        });
    }

    /** Bloğu siler: gelecekteki oluşumlar iptal edilir, geçmiş kayıtlar istatistik için kalır. */
    public function delete(User $admin, RecurringBlock $block): void
    {
        DB::transaction(function () use ($admin, $block) {
            $block->reservations()->active()->where('starts_at', '>', now())->get()
                ->each(fn ($r) => $this->reservations->cancelByStaff($admin, $r, __('Sabit blok kaldırıldı'), false));
            Audit::log('block.deleted', $block, ['title' => $block->title]);
            $block->delete();
        });
    }

    public function closureConflicts(?int $facilityId, $start, $end): Collection
    {
        return Reservation::active()->with('user', 'facility')
            ->when($facilityId, fn ($q) => $q->where('facility_id', $facilityId))
            ->overlapping($start, $end)->where('kind', '!=', 'block')->get();
    }

    public function createClosure(User $admin, array $data): Closure
    {
        return DB::transaction(function () use ($admin, $data) {
            $closure = Closure::create($data + ['created_by' => $admin->id]);
            foreach ($this->closureConflicts($closure->facility_id, $closure->starts_at, $closure->ends_at) as $r) {
                $this->reservations->cancelByStaff($admin, $r, $closure->reason);
            }
            // Kapatmaya denk gelen sabit blok oluşumları sessizce iptal edilir.
            Reservation::active()->where('kind', 'block')
                ->when($closure->facility_id, fn ($q) => $q->where('facility_id', $closure->facility_id))
                ->overlapping($closure->starts_at, $closure->ends_at)->get()
                ->each(fn ($r) => $this->reservations->cancelByStaff($admin, $r, $closure->reason, false));
            Audit::log('closure.created', $closure, $data);

            return $closure;
        });
    }
}
