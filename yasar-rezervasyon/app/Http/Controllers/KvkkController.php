<?php

namespace App\Http\Controllers;

use App\Models\KvkkConsent;
use App\Models\KvkkText;
use App\Services\Audit;
use Illuminate\Http\Request;

class KvkkController extends Controller
{
    public function show()
    {
        $text = KvkkText::current();
        if (! $text) {
            return redirect()->route('calendar');
        }

        return view('kvkk.show', compact('text'));
    }

    public function accept(Request $request)
    {
        $request->validate(['accept' => ['accepted']]);
        $text = KvkkText::current();
        if ($text) {
            KvkkConsent::firstOrCreate(
                ['user_id' => $request->user()->id, 'kvkk_text_id' => $text->id],
                ['accepted_at' => now(), 'ip_address' => $request->ip(), 'user_agent' => mb_substr((string) $request->userAgent(), 0, 500)],
            );
            Audit::log('kvkk.accepted', $text);
        }

        return redirect()->intended(route('calendar'));
    }
}
