<?php

namespace App\Http\Controllers;

use App\Models\Facility;
use App\Models\Reservation;
use App\Models\Team;
use App\Services\Availability;
use App\Services\ReservationService;
use App\Services\UserDirectory;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Validation\ValidationException;

class ReservationController extends Controller
{
    public function index(Request $request)
    {
        $user = $request->user();
        $tab = in_array($request->query('tab'), ['upcoming', 'past', 'participating'], true) ? $request->query('tab') : 'upcoming';
        $with = ['facility', 'activity', 'units', 'team', 'equipmentItems.equipment', 'participantUsers', 'user'];

        $query = match ($tab) {
            'participating' => Reservation::whereHas('participants', fn ($q) => $q->where('user_id', $user->id))
                ->where('ends_at', '>=', now()->subDays(30))->orderBy('starts_at'),
            'past' => Reservation::where('user_id', $user->id)->where('kind', '!=', 'block')
                ->where(fn ($q) => $q->where('ends_at', '<', now())->orWhereNotIn('status', Reservation::ACTIVE))
                ->orderByDesc('starts_at'),
            default => Reservation::where('user_id', $user->id)->where('kind', '!=', 'block')->active()
                ->where('ends_at', '>=', now())->orderBy('starts_at'),
        };

        return view('reservations.index', [
            'tab' => $tab,
            'reservations' => $query->with($with)->paginate(20)->withQueryString(),
            'sanction' => $user->activeSanction(),
        ]);
    }

    public function create(Request $request, Availability $availability)
    {
        $user = $request->user();
        $facilities = Facility::where('is_active', true)->orderBy('sort')->get()
            ->filter(fn ($f) => $f->allowsAnyRole($user->bookingRoles()))->values();
        abort_if($facilities->isEmpty(), 403);
        $facility = $facilities->firstWhere('id', (int) $request->query('facility', old('facility_id'))) ?? $facilities->first();
        $facility->load('units', 'hours', 'equipment');

        $activities = $facility->activities()->where('is_active', true)->get()
            ->filter(fn ($a) => $a->allowsAnyRole($user->bookingRoles()))->values();
        $date = rescue(fn () => Carbon::parse($request->query('date', old('date', today()->toDateString()))), today(), false);

        $times = collect($availability->daySlots($facility, $date))->map(fn ($m) => Availability::minutesToTime($m));
        $endTimes = collect($availability->daySlots($facility, $date))->map(fn ($m) => Availability::minutesToTime($m + $facility->slot_minutes));

        $teams = $user->is_coach && ! $user->is_sks_admin
            ? $user->coachedTeams()->where('is_active', true)->get()
            : Team::where('is_active', true)->orderBy('name')->get();

        return view('reservations.create', [
            'facilities' => $facilities, 'facility' => $facility, 'activities' => $activities, 'date' => $date,
            'times' => $times, 'endTimes' => $endTimes, 'teams' => $teams,
            'start' => $request->query('start', old('start')), 'unitId' => $request->query('unit', old('unit_id')),
        ]);
    }

    public function store(Request $request, ReservationService $service, UserDirectory $directory)
    {
        $data = $request->validate([
            'facility_id' => ['required', 'integer', 'exists:facilities,id'],
            'activity_type_id' => ['required', 'integer'],
            'date' => ['required', 'date_format:Y-m-d'],
            'start' => ['required', 'date_format:H:i'],
            'end' => ['required', 'date_format:H:i'],
            'unit_id' => ['nullable', 'integer'],
            'team_id' => ['nullable', 'integer'],
            'participant_numbers' => ['nullable', 'string', 'max:2000'],
            'participant_count' => ['nullable', 'integer', 'min:1', 'max:500'],
            'equipment' => ['nullable', 'array'],
            'equipment.*' => ['nullable', 'integer', 'min:0', 'max:100'],
            'notes' => ['nullable', 'string', 'max:500'],
        ]);

        $ids = [];
        $numbers = collect(preg_split('/[\s,;]+/', (string) ($data['participant_numbers'] ?? '')))->filter()->unique();
        if ($numbers->count() > 100) {
            throw ValidationException::withMessages(['participant_numbers' => __('En fazla 100 katılımcı eklenebilir.')]);
        }
        foreach ($numbers as $no) {
            $u = $directory->findOrImport($no);
            if (! $u) {
                throw ValidationException::withMessages(['participant_numbers' => __(':no numaralı kullanıcı bulunamadı.', ['no' => $no])]);
            }
            $ids[] = $u->id;
        }
        $data['participants'] = $ids;

        $reservation = $service->create($request->user(), $data);

        return redirect()->route('reservations.index')
            ->with('success', __('Talebiniz alındı, SKS onayı bekleniyor. Kod: :code', ['code' => $reservation->code]));
    }

    public function cancel(Request $request, Reservation $reservation, ReservationService $service)
    {
        $wasLate = $reservation->status === Reservation::APPROVED && now()->gt($reservation->cancelDeadline());
        $service->cancelByOwner($request->user(), $reservation);

        return back()->with($wasLate ? 'warning' : 'success', $wasLate
            ? __('Rezervasyon iptal edildi. Bu iptal geç iptal olarak kaydedildi.')
            : __('Rezervasyon iptal edildi.'));
    }

    public function leave(Request $request, Reservation $reservation, ReservationService $service)
    {
        $service->leave($request->user(), $reservation);

        return back()->with('success', __('Katılımcı listesinden çıktınız.'));
    }
}
