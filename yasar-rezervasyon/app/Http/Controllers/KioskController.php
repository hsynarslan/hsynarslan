<?php

namespace App\Http\Controllers;

use App\Models\Facility;
use App\Services\Audit;
use App\Services\CheckinToken;
use BaconQrCode\Renderer\Image\SvgImageBackEnd;
use BaconQrCode\Renderer\ImageRenderer;
use BaconQrCode\Renderer\RendererStyle\RendererStyle;
use BaconQrCode\Writer;
use Illuminate\Http\Request;

/** Tesis girişindeki tablet ekranı: 30 saniyede bir değişen QR kodu gösterir. Görevli veya SKS Admin oturumuyla açılır. */
class KioskController extends Controller
{
    public function show(Request $request, Facility $facility)
    {
        abort_unless($request->user()->canAttendFacility($facility->id), 403);
        Audit::log('kiosk.opened', $facility);

        return view('kiosk.show', ['facility' => $facility, 'period' => CheckinToken::PERIOD]);
    }

    public function qr(Request $request, Facility $facility)
    {
        abort_unless($request->user()->canAttendFacility($facility->id), 403);
        $svg = (new Writer(new ImageRenderer(new RendererStyle(520, 2), new SvgImageBackEnd)))->writeString(CheckinToken::url($facility));

        return response($svg, 200, ['Content-Type' => 'image/svg+xml', 'Cache-Control' => 'no-store, private']);
    }
}
