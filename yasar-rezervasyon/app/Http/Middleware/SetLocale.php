<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class SetLocale
{
    public function handle(Request $request, Closure $next): Response
    {
        $locale = $request->user()?->locale ?? $request->session()->get('locale', 'tr');
        app()->setLocale(in_array($locale, ['tr', 'en'], true) ? $locale : 'tr');

        return $next($request);
    }
}
