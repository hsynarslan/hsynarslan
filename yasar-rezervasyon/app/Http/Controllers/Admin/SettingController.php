<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Setting;
use App\Services\Audit;
use Illuminate\Http\Request;

class SettingController extends Controller
{
    public function edit()
    {
        $values = collect(Setting::DEFAULTS)->map(fn ($v, $k) => Setting::get($k));

        return view('admin.settings', compact('values'));
    }

    public function update(Request $request)
    {
        $rules = collect(Setting::DEFAULTS)->map(fn () => ['required', 'integer', 'min:0', 'max:10000'])->all();
        $rules['retention_years'] = ['required', 'integer', 'min:1', 'max:20'];
        $rules['sanction_threshold'] = ['required', 'integer', 'min:1', 'max:100'];
        $data = $request->validate($rules);
        $before = collect(Setting::DEFAULTS)->map(fn ($v, $k) => Setting::get($k))->all();
        foreach ($data as $k => $v) {
            Setting::put($k, (int) $v);
        }
        Audit::log('settings.updated', null, ['before' => $before, 'after' => $data]);

        return back()->with('success', __('Ayarlar kaydedildi.'));
    }
}
