<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Equipment;
use App\Models\Facility;
use App\Services\Audit;
use Illuminate\Http\Request;

class EquipmentController extends Controller
{
    public function index()
    {
        return view('admin.equipment.index', [
            'items' => Equipment::with('facilities')->orderBy('name_tr')->get(),
            'facilities' => Facility::orderBy('sort')->get(),
        ]);
    }

    public function store(Request $request)
    {
        [$data, $facilities] = $this->validated($request);
        $item = Equipment::create($data + ['is_active' => true]);
        $item->facilities()->sync($facilities);
        Audit::log('equipment.created', $item, $data + ['facilities' => $facilities]);

        return back()->with('success', __('Ekipman eklendi.'));
    }

    public function update(Request $request, Equipment $equipment)
    {
        [$data, $facilities] = $this->validated($request);
        $equipment->fill($data);
        $equipment->is_active = $request->boolean('is_active');
        $equipment->save();
        $equipment->facilities()->sync($facilities);
        Audit::log('equipment.updated', $equipment, $data + ['facilities' => $facilities, 'is_active' => $equipment->is_active]);

        return back()->with('success', __('Ekipman kaydedildi.'));
    }

    private function validated(Request $request): array
    {
        $data = $request->validate([
            'name_tr' => ['required', 'string', 'max:150'],
            'name_en' => ['required', 'string', 'max:150'],
            'total_stock' => ['required', 'integer', 'min:0', 'max:1000'],
            'facilities' => ['array'],
            'facilities.*' => ['integer', 'exists:facilities,id'],
        ]);

        return [collect($data)->except('facilities')->all(), $data['facilities'] ?? []];
    }
}
