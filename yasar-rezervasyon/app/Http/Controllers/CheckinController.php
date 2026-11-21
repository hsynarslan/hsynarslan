<?php

namespace App\Http\Controllers;

use App\Models\Facility;
use App\Services\Audit;
use App\Services\CheckinToken;
use App\Services\ReservationService;
use Illuminate\Http\Request;

/**
 * QR ile giriş. GET onay sayfası, POST kayıt (durum değiştiren işlem CSRF korumalı POST'tur).
 *  - Dinamik QR (varsayılan): /checkin/{tesis}/{pencere}/{imza} — girişteki tabletin 30 sn'de bir yenilediği, kısa ömürlü bağlantı.
 *  - Sabit QR: /checkin/{token} — yalnızca tesis "sabit QR" moduna alınmışsa kabul edilir.
 */
class CheckinController extends Controller
{
    public function show(string $token)
    {
        $facility = Facility::where('checkin_token', $token)->firstOrFail();
        if ($facility->usesDynamicQr()) {
            return view('checkin.expired', compact('facility'));
        }

        return view('checkin.show', ['facility' => $facility, 'action' => route('checkin.store', $token)]);
    }

    public function store(Request $request, string $token, ReservationService $service)
    {
        $facility = Facility::where('checkin_token', $token)->firstOrFail();
        if ($facility->usesDynamicQr()) {
            return view('checkin.expired', compact('facility'));
        }
        $reservation = $service->checkin($request->user(), $facility, 'qr_static');

        return view('checkin.result', compact('facility', 'reservation'));
    }

    public function showDynamic(Facility $facility, int $window, string $sig)
    {
        if (! CheckinToken::valid($facility, $window, $sig)) {
            return view('checkin.expired', compact('facility'));
        }

        return view('checkin.show', ['facility' => $facility, 'action' => route('checkin.dynamic.store', [$facility, $window, $sig])]);
    }

    public function storeDynamic(Request $request, Facility $facility, int $window, string $sig, ReservationService $service)
    {
        if (! CheckinToken::valid($facility, $window, $sig)) {
            Audit::log('checkin.expired_token', $facility, ['window' => $window]);

            return view('checkin.expired', compact('facility'));
        }
        $reservation = $service->checkin($request->user(), $facility, 'qr_dynamic');

        return view('checkin.result', compact('facility', 'reservation'));
    }
}
