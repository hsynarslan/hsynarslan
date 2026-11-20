<?php

namespace App\Http\Controllers;

use App\Models\Facility;
use App\Services\ReservationService;
use Illuminate\Http\Request;

/** QR check-in: GET onay sayfası, POST kayıt (durum değiştiren işlem CSRF korumalı POST'tur). */
class CheckinController extends Controller
{
    public function show(string $token)
    {
        $facility = Facility::where('checkin_token', $token)->firstOrFail();

        return view('checkin.show', compact('facility', 'token'));
    }

    public function store(Request $request, string $token, ReservationService $service)
    {
        $facility = Facility::where('checkin_token', $token)->firstOrFail();
        $reservation = $service->checkin($request->user(), $facility);

        return view('checkin.result', compact('facility', 'reservation'));
    }
}
