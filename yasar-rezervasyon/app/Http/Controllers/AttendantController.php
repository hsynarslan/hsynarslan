<?php

namespace App\Http\Controllers;

use App\Models\Facility;
use App\Models\Reservation;
use App\Models\ReservationEquipment;
use App\Services\ReservationService;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;

class AttendantController extends Controller
{
    public function index(Request $request)
    {
        $ids = $request->user()->attendableFacilityIds();
        $facilities = Facility::where('is_active', true)->when($ids !== null, fn ($q) => $q->whereIn('id', $ids))->orderBy('sort')->get();
        $facility = $facilities->firstWhere('id', (int) $request->query('facility')) ?? $facilities->first();
        $date = rescue(fn () => Carbon::parse($request->query('date', today()->toDateString())), today(), false);

        $reservations = $facility ? Reservation::with('activity', 'units', 'user', 'team', 'block', 'checkins.user', 'participantUsers', 'equipmentItems.equipment')
            ->where('facility_id', $facility->id)->where('status', Reservation::APPROVED)
            ->whereBetween('starts_at', [$date->copy()->startOfDay(), $date->copy()->endOfDay()])
            ->orderBy('starts_at')->get() : collect();

        return view('attendant.index', compact('facilities', 'facility', 'date', 'reservations'));
    }

    public function attendance(Request $request, Reservation $reservation, ReservationService $service)
    {
        $data = $request->validate(['attendance' => ['required', 'in:attended,no_show']]);
        $service->markAttendance($request->user(), $reservation, $data['attendance']);

        return back()->with('success', __('Katılım kaydedildi.'));
    }

    public function equipment(Request $request, ReservationEquipment $item, ReservationService $service)
    {
        $data = $request->validate([
            'action' => ['required', 'in:deliver,return'],
            'condition' => ['nullable', 'required_if:action,return', 'in:ok,damaged,missing'],
            'note' => ['nullable', 'string', 'max:500'],
        ]);
        $service->equipmentAction($request->user(), $item, $data['action'], $data['condition'] ?? null, $data['note'] ?? null);

        return back()->with('success', __('Ekipman kaydı güncellendi.'));
    }
}
