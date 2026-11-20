<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Holiday;
use App\Services\Audit;
use App\Services\BlockService;
use App\Services\ReservationService;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;

class HolidayController extends Controller
{
    public function index(Request $request)
    {
        $year = (int) $request->query('year', today()->year);

        return view('admin.holidays.index', [
            'year' => $year,
            'holidays' => Holiday::whereYear('date', $year)->orderBy('date')->get(),
        ]);
    }

    public function store(Request $request, BlockService $blocks, ReservationService $reservations)
    {
        $data = $request->validate([
            'date' => ['required', 'date', 'unique:holidays,date'],
            'name_tr' => ['required', 'string', 'max:150'],
            'name_en' => ['required', 'string', 'max:150'],
            'closed_from' => ['nullable', 'date_format:H:i'],
        ]);
        $holiday = Holiday::create($data);
        $day = Carbon::parse($data['date']);
        $from = $data['closed_from'] ? $day->copy()->setTimeFromTimeString($data['closed_from']) : $day->copy()->startOfDay();
        $conflicts = $blocks->closureConflicts(null, $from, $day->copy()->endOfDay());
        foreach ($conflicts as $r) {
            $reservations->cancelByStaff($request->user(), $r, $holiday->name_tr);
        }
        Audit::log('holiday.created', $holiday, $data + ['cancelled' => $conflicts->pluck('id')->all()]);

        return back()->with('success', __('Tatil eklendi. Çakışan :n rezervasyon iptal edildi.', ['n' => $conflicts->count()]));
    }

    public function destroy(Holiday $holiday)
    {
        Audit::log('holiday.deleted', $holiday, $holiday->only('date', 'name_tr'));
        $holiday->delete();

        return back()->with('success', __('Tatil silindi.'));
    }
}
