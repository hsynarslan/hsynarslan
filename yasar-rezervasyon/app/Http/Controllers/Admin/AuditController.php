<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\AuditLog;
use App\Services\Audit;
use Illuminate\Http\Request;

class AuditController extends Controller
{
    public function index(Request $request)
    {
        $logs = AuditLog::with('user')
            ->when($request->filled('action'), fn ($q) => $q->where('action', 'like', $request->query('action').'%'))
            ->when($request->filled('user'), fn ($q) => $q->whereHas('user', fn ($u) => $u->where('username', $request->query('user'))->orWhere('number', $request->query('user'))))
            ->when($request->filled('from'), fn ($q) => $q->where('created_at', '>=', $request->date('from')))
            ->when($request->filled('to'), fn ($q) => $q->where('created_at', '<=', $request->date('to')?->endOfDay()))
            ->orderByDesc('id')->paginate(50)->withQueryString();
        $broken = $request->boolean('verify') ? (Audit::verify() ?? 0) : null;
        Audit::log('audit.viewed', null, $request->only('action', 'user', 'from', 'to', 'verify'));

        return view('admin.audit', compact('logs', 'broken'));
    }
}
