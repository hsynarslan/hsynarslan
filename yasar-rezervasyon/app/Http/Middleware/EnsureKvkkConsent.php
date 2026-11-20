<?php

namespace App\Http\Middleware;

use App\Models\KvkkConsent;
use App\Models\KvkkText;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/** Güncel KVKK aydınlatma metni onaylanmadan sistem kullanılamaz (ISO 27001 A.5.34). */
class EnsureKvkkConsent
{
    public function handle(Request $request, Closure $next): Response
    {
        $text = KvkkText::current();
        if ($text && ! KvkkConsent::where('user_id', $request->user()->id)->where('kvkk_text_id', $text->id)->exists()) {
            session()->put('url.intended', $request->fullUrl());

            return redirect()->route('kvkk.show');
        }

        return $next($request);
    }
}
