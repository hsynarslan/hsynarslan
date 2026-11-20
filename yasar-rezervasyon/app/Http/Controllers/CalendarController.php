<?php

namespace App\Http\Controllers;

use App\Models\Closure;
use App\Models\Facility;
use App\Models\Holiday;
use App\Models\Reservation;
use App\Services\Availability;
use Carbon\CarbonPeriod;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;

class CalendarController extends Controller
{
    public function index(Request $request, Availability $availability)
    {
        $user = $request->user();
        $facilities = Facility::where('is_active', true)->orderBy('sort')->orderBy('id')->get()
            ->filter(fn ($f) => $f->allowsAnyRole($user->bookingRoles()) || $user->isApprover() || $user->is_attendant)->values();
        if ($facilities->isEmpty()) {
            return view('calendar.empty');
        }

        $facility = $facilities->firstWhere('id', (int) $request->query('facility')) ?? $facilities->first();
        $facility->load('units', 'hours');
        $date = rescue(fn () => Carbon::parse($request->query('date', today()->toDateString()))->startOfDay(), today(), false);
        $view = $request->query('view') === 'week' ? 'week' : 'day';
        $canSeeNames = $user->is_sks_admin || $user->is_attendant || $user->canApproveFacility($facility->id);
        $canBook = $facility->allowsAnyRole($user->bookingRoles());

        $days = $view === 'week'
            ? collect(CarbonPeriod::create($date->copy()->startOfWeek(), $date->copy()->endOfWeek()))->map(fn ($d) => Carbon::instance($d))
            : collect([$date]);
        $from = $days->first()->copy()->startOfDay();
        $to = $days->last()->copy()->endOfDay();

        $locks = $availability->dayLocks($facility, $from, $to);
        $holidays = Holiday::whereBetween('date', [$from->toDateString(), $to->toDateString()])->get()->keyBy(fn ($h) => $h->date->toDateString());
        $closures = Closure::where(fn ($q) => $q->whereNull('facility_id')->orWhere('facility_id', $facility->id))
            ->where('starts_at', '<', $to)->where('ends_at', '>', $from)->get();

        // Satırlar: haftadaki tüm günlerin slotlarının birleşimi.
        $slotMinutes = $days->flatMap(fn ($d) => $availability->daySlots($facility, $d))->unique()->sort()->values();

        $cell = function (Carbon $day, int $minute, $unit) use ($facility, $locks, $holidays, $closures, $availability, $user) {
            $start = $day->copy()->addMinutes($minute);
            $end = $start->copy()->addMinutes($facility->slot_minutes);
            if (! in_array($minute, $availability->daySlots($facility, $day), true)) {
                return ['state' => 'closed', 'label' => ''];
            }
            $holiday = $holidays->get($day->toDateString());
            if ($holiday && (! $holiday->closed_from || $end->gt($day->copy()->setTimeFromTimeString($holiday->closed_from)))) {
                return ['state' => 'closed', 'label' => $holiday->name];
            }
            $closure = $closures->first(fn ($c) => $c->starts_at->lt($end) && $c->ends_at->gt($start));
            if ($closure) {
                return ['state' => 'closed', 'label' => $closure->reason];
            }

            $unitLocks = $locks->filter(fn ($l) => ($unit === null || $l->facility_unit_id === $unit->id)
                && $l->starts_at->lt($end) && $l->ends_at->gt($start));
            if ($unit === null) {
                $busy = $unitLocks->pluck('facility_unit_id')->unique()->count();
                $total = $facility->units->count();
                $state = $busy === 0 ? 'free' : ($busy >= $total ? 'busy' : 'partial');
                if ($start->lt(now()) && $state !== 'busy') {
                    $state = 'past';
                }

                return ['state' => $state, 'label' => __(':free/:total boş', ['free' => $total - $busy, 'total' => $total])];
            }
            $lock = $unitLocks->first();
            if ($lock) {
                return ['state' => $lock->reservation->status === 'pending' ? 'pending' : 'approved', 'lock' => $lock,
                    'mine' => $lock->reservation->user_id === $user->id];
            }

            return ['state' => $start->lt(now()) ? 'past' : 'free', 'label' => ''];
        };

        $next = Reservation::with('facility', 'activity', 'units')->involving($user->id)->active()
            ->where('kind', '!=', 'block')->where('ends_at', '>', now())->orderBy('starts_at')->first();

        return view('calendar.index', compact('facilities', 'facility', 'date', 'view', 'days', 'slotMinutes', 'cell', 'canSeeNames', 'canBook', 'next'));
    }
}
