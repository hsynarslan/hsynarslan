<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/** Rol tabanlı erişim (ISO 27001 A.5.15 Erişim kontrolü). Kullanım: role:sks_admin,attendant */
class RequireRole
{
    public function handle(Request $request, Closure $next, string ...$roles): Response
    {
        $u = $request->user();
        foreach ($roles as $role) {
            $ok = match ($role) {
                'sks_admin' => $u->is_sks_admin,
                'attendant' => $u->is_attendant || $u->is_sks_admin,
                'approver' => $u->isApprover(),
                'coach' => $u->is_coach || $u->is_sks_admin,
                default => false,
            };
            if ($ok) {
                return $next($request);
            }
        }
        abort(403);
    }
}
