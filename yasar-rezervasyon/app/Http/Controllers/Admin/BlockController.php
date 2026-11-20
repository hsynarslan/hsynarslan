<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Facility;
use App\Models\RecurringBlock;
use App\Models\Team;
use App\Services\BlockService;
use Illuminate\Http\Request;

class BlockController extends Controller
{
    public function index(Request $request)
    {
        $facilities = Facility::with('units', 'activities')->orderBy('sort')->get();

        return view('admin.blocks.index', [
            'blocks' => RecurringBlock::with('facility', 'team')->where('ends_on', '>=', today())->orderBy('starts_on')->get(),
            'facilities' => $facilities,
            'facility' => $facilities->firstWhere('id', (int) $request->query('facility', old('facility_id'))) ?? $facilities->first(),
            'teams' => Team::where('is_active', true)->orderBy('name')->get(),
        ]);
    }

    public function store(Request $request, BlockService $service)
    {
        $data = $request->validate([
            'facility_id' => ['required', 'integer', 'exists:facilities,id'],
            'activity_type_id' => ['required', 'integer', 'exists:activity_types,id'],
            'team_id' => ['nullable', 'integer', 'exists:teams,id'],
            'title' => ['required', 'string', 'max:150'],
            'weekdays' => ['required', 'array', 'min:1'],
            'weekdays.*' => ['integer', 'between:1,7'],
            'unit_ids' => ['required', 'array', 'min:1'],
            'unit_ids.*' => ['integer', 'exists:facility_units,id'],
            'start_time' => ['required', 'date_format:H:i'],
            'end_time' => ['required', 'date_format:H:i', 'after:start_time'],
            'starts_on' => ['required', 'date', 'after_or_equal:today'],
            'ends_on' => ['required', 'date', 'after_or_equal:starts_on', 'before:'.today()->addYear()->toDateString()],
            'cancel_reason' => ['nullable', 'string', 'max:255'],
        ]);
        $facility = Facility::findOrFail($data['facility_id']);
        abort_unless($facility->units()->whereIn('id', $data['unit_ids'])->count() === count($data['unit_ids']), 422);
        abort_unless($facility->activities()->whereKey($data['activity_type_id'])->exists(), 422);
        $data['weekdays'] = array_map('intval', $data['weekdays']);
        $data['unit_ids'] = array_map('intval', $data['unit_ids']);

        $preview = $service->preview($data);
        if (! $request->boolean('confirm')) {
            return back()->withInput()->with('preview', [
                'count' => count($preview['occurrences']),
                'skipped' => $preview['skipped'],
                'conflicts' => $preview['conflicts']->map(fn ($r) => ['code' => $r->code, 'user' => $r->user?->name,
                    'time' => $r->starts_at->format('d.m.Y H:i').'–'.$r->ends_at->format('H:i'), 'block' => $r->isBlock()])->all(),
            ]);
        }
        $reason = ($data['cancel_reason'] ?? null) ?: $data['title'];
        $service->create($request->user(), collect($data)->except('cancel_reason')->all(), $reason);

        return redirect()->route('admin.blocks.index')->with('success', __('Sabit blok oluşturuldu: :n oturum.', ['n' => count($preview['occurrences'])]));
    }

    public function destroy(Request $request, RecurringBlock $block, BlockService $service)
    {
        $service->delete($request->user(), $block);

        return back()->with('success', __('Sabit blok kaldırıldı; gelecekteki oturumlar iptal edildi.'));
    }
}
