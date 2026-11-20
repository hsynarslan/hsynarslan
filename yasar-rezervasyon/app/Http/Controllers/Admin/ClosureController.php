<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Closure;
use App\Models\Facility;
use App\Services\Audit;
use App\Services\BlockService;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;

class ClosureController extends Controller
{
    public function index()
    {
        return view('admin.closures.index', [
            'closures' => Closure::with('facility')->where('ends_at', '>=', now()->subDays(30))->orderBy('starts_at')->get(),
            'facilities' => Facility::orderBy('sort')->get(),
        ]);
    }

    public function store(Request $request, BlockService $blocks)
    {
        $data = $request->validate([
            'facility_id' => ['nullable', 'integer', 'exists:facilities,id'],
            'starts_at' => ['required', 'date'],
            'ends_at' => ['required', 'date', 'after:starts_at'],
            'reason' => ['required', 'string', 'max:255'],
        ]);
        $conflicts = $blocks->closureConflicts($data['facility_id'] ?? null, Carbon::parse($data['starts_at']), Carbon::parse($data['ends_at']));
        if ($conflicts->isNotEmpty() && ! $request->boolean('confirm')) {
            return back()->withInput()->with('conflicts', $conflicts->map(fn ($r) => [
                'code' => $r->code, 'facility' => $r->facility->name, 'user' => $r->user?->name,
                'time' => $r->starts_at->format('d.m.Y H:i').'–'.$r->ends_at->format('H:i'),
            ])->all());
        }
        $blocks->createClosure($request->user(), $data);

        return redirect()->route('admin.closures.index')->with('success', __('Kapatma kaydedildi. Çakışan :n rezervasyon iptal edildi.', ['n' => $conflicts->count()]));
    }

    public function destroy(Closure $closure)
    {
        Audit::log('closure.deleted', $closure, $closure->only('facility_id', 'reason'));
        $closure->delete();

        return back()->with('success', __('Kapatma kaldırıldı.'));
    }
}
