<?php

namespace App\Services;

use App\Models\Facility;
use App\Models\Reservation;
use App\Models\ReservationEquipment;
use App\Models\Sanction;
use App\Support\Roles;
use Carbon\CarbonPeriod;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;

/** Rapor verileri. Tesis onaycıları yalnızca kendi tesislerini görür. */
class ReportService
{
    public function __construct(private readonly Availability $availability) {}

    public function build(Carbon $from, Carbon $to, Collection $facilities): array
    {
        $ids = $facilities->pluck('id');
        $base = fn () => Reservation::whereIn('facility_id', $ids)->whereBetween('starts_at', [$from, $to]);
        $all = $base()->with('user', 'team', 'decider')->withCount('reservationUnits')->get();
        $userRes = $all->where('kind', '!=', 'block');

        return [
            'occupancy' => $this->occupancy($from, $to, $facilities, $all),
            'heatmap' => $this->heatmap($all->where('status', Reservation::APPROVED)),
            'byRole' => $this->byRole($userRes),
            'topUsers' => $userRes->whereNotNull('user_id')->groupBy('user_id')
                ->map(fn ($g) => ['name' => $g->first()->user?->name, 'number' => $g->first()->user?->number, 'count' => $g->count()])
                ->sortByDesc('count')->take(10)->values()->all(),
            'attendance' => $this->attendance($userRes),
            'sanctioned' => Sanction::with('user')->where('starts_at', '<=', $to)->where('ends_at', '>=', $from)->get()
                ->map(fn ($s) => ['name' => $s->user->name, 'number' => $s->user->number, 'from' => $s->starts_at->format('d.m.Y'),
                    'to' => $s->ends_at->format('d.m.Y'), 'reason' => $s->reason, 'lifted' => (bool) $s->lifted_at])->all(),
            'approval' => $this->approval($userRes),
            'teams' => $all->whereNotNull('team_id')->where('status', Reservation::APPROVED)->groupBy('team_id')
                ->map(fn ($g) => ['team' => $g->first()->team?->name, 'count' => $g->count(),
                    'hours' => round($g->sum(fn ($r) => $r->starts_at->diffInMinutes($r->ends_at)) / 60, 1)])
                ->sortByDesc('hours')->values()->all(),
            'equipment' => ReservationEquipment::with('equipment', 'reservation.user')
                ->whereIn('return_condition', ['damaged', 'missing'])
                ->whereHas('reservation', fn ($q) => $q->whereIn('facility_id', $ids)->whereBetween('starts_at', [$from, $to]))
                ->get()->map(fn ($e) => ['code' => $e->reservation->code, 'equipment' => $e->equipment->name, 'quantity' => $e->quantity,
                    'condition' => $e->conditionLabel(), 'user' => $e->reservation->user?->name, 'note' => $e->note])->all(),
        ];
    }

    private function occupancy(Carbon $from, Carbon $to, Collection $facilities, Collection $all): array
    {
        $rows = [];
        foreach ($facilities as $f) {
            $f->loadMissing('units', 'hours');
            $available = 0;
            foreach (CarbonPeriod::create($from->copy()->startOfDay(), $to->copy()->startOfDay()) as $day) {
                $available += count($this->availability->daySlots($f, Carbon::instance($day))) * $f->slot_minutes * $f->units->count();
            }
            $booked = $all->where('facility_id', $f->id)->where('status', Reservation::APPROVED)
                ->sum(fn ($r) => $r->starts_at->diffInMinutes($r->ends_at) * max(1, $r->reservation_units_count));
            $rows[] = [
                'facility' => $f->name,
                'reservations' => $all->where('facility_id', $f->id)->where('status', Reservation::APPROVED)->count(),
                'booked_hours' => round($booked / 60, 1),
                'available_hours' => round($available / 60, 1),
                'rate' => $available ? round($booked / $available * 100, 1) : 0,
            ];
        }

        return $rows;
    }

    /** Gün (1-7) × saat (0-23) onaylı rezervasyon sayısı. */
    private function heatmap(Collection $approved): array
    {
        $grid = [];
        foreach ($approved as $r) {
            $grid[$r->starts_at->isoWeekday()][$r->starts_at->hour] = ($grid[$r->starts_at->isoWeekday()][$r->starts_at->hour] ?? 0) + 1;
        }

        return $grid;
    }

    private function byRole(Collection $res): array
    {
        return $res->whereNotNull('user_id')->groupBy(fn ($r) => $r->user->bookingRole())
            ->map(fn ($g, $role) => ['role' => Roles::label($role), 'count' => $g->count(), 'users' => $g->pluck('user_id')->unique()->count()])
            ->values()->all();
    }

    private function attendance(Collection $res): array
    {
        $approvedPast = $res->where('status', Reservation::APPROVED)->filter(fn ($r) => $r->ends_at->isPast());
        $noShow = $approvedPast->where('attendance', 'no_show')->count();

        return [
            'total' => $res->count(),
            'approved_past' => $approvedPast->count(),
            'attended' => $approvedPast->where('attendance', 'attended')->count(),
            'no_show' => $noShow,
            'no_show_rate' => $approvedPast->count() ? round($noShow / $approvedPast->count() * 100, 1) : 0,
            'cancelled' => $res->where('status', Reservation::CANCELLED)->count(),
            'late_cancel' => $res->where('is_late_cancel', true)->count(),
        ];
    }

    private function approval(Collection $res): array
    {
        $decided = $res->whereNotNull('decided_at');

        return [
            'pending' => $res->where('status', Reservation::PENDING)->count(),
            'approved' => $res->where('status', Reservation::APPROVED)->count(),
            'rejected' => $res->where('status', Reservation::REJECTED)->count(),
            'expired' => $res->where('status', Reservation::EXPIRED)->count(),
            'avg_minutes' => $decided->count() ? round($decided->avg(fn ($r) => $r->created_at->diffInMinutes($r->decided_at))) : null,
            'by_approver' => $decided->groupBy('decided_by')->map(fn ($g) => [
                'name' => $g->first()->decider?->name,
                'approved' => $g->where('status', Reservation::APPROVED)->count(),
                'rejected' => $g->where('status', Reservation::REJECTED)->count(),
            ])->values()->all(),
        ];
    }

    public function facilitiesFor($user): Collection
    {
        $ids = $user->approvableFacilityIds();

        return Facility::orderBy('sort')->when($ids !== null, fn ($q) => $q->whereIn('id', $ids))->get();
    }
}
