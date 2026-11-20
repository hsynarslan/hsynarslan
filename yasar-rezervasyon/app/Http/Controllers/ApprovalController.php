<?php

namespace App\Http\Controllers;

use App\Models\Reservation;
use App\Services\ReservationService;
use Illuminate\Http\Request;

class ApprovalController extends Controller
{
    public function index(Request $request)
    {
        $ids = $request->user()->approvableFacilityIds();
        $pending = Reservation::with('facility', 'activity', 'units', 'user', 'team', 'equipmentItems.equipment', 'participantUsers')
            ->where('status', Reservation::PENDING)
            ->where('starts_at', '>', now())
            ->when($ids !== null, fn ($q) => $q->whereIn('facility_id', $ids))
            ->orderBy('starts_at')->get();

        $upcoming = Reservation::with('facility', 'activity', 'units', 'user')
            ->where('status', Reservation::APPROVED)->where('kind', '!=', 'block')
            ->where('starts_at', '>', now())->where('starts_at', '<', now()->addDays(14))
            ->when($ids !== null, fn ($q) => $q->whereIn('facility_id', $ids))
            ->orderBy('starts_at')->limit(100)->get();

        return view('approvals.index', compact('pending', 'upcoming'));
    }

    public function approve(Request $request, Reservation $reservation, ReservationService $service)
    {
        $service->approve($request->user(), $reservation);

        return back()->with('success', __(':code onaylandı.', ['code' => $reservation->code]));
    }

    public function reject(Request $request, Reservation $reservation, ReservationService $service)
    {
        $data = $request->validate(['reason' => ['required', 'string', 'min:3', 'max:500']]);
        $service->reject($request->user(), $reservation, $data['reason']);

        return back()->with('success', __(':code reddedildi.', ['code' => $reservation->code]));
    }

    public function cancel(Request $request, Reservation $reservation, ReservationService $service)
    {
        $data = $request->validate(['reason' => ['required', 'string', 'min:3', 'max:500']]);
        $service->cancelByStaff($request->user(), $reservation, $data['reason']);

        return back()->with('success', __(':code iptal edildi.', ['code' => $reservation->code]));
    }
}
