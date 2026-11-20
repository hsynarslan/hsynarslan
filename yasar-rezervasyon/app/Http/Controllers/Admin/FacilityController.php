<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\ActivityType;
use App\Models\Facility;
use App\Models\FacilityRule;
use App\Models\FacilityUnit;
use App\Models\ReservationUnit;
use App\Models\User;
use App\Services\Audit;
use App\Support\Roles;
use BaconQrCode\Renderer\Image\SvgImageBackEnd;
use BaconQrCode\Renderer\ImageRenderer;
use BaconQrCode\Renderer\RendererStyle\RendererStyle;
use BaconQrCode\Writer;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class FacilityController extends Controller
{
    public function index()
    {
        $facilities = Facility::withCount('units', 'activities', 'approvers')->orderBy('sort')->orderBy('id')->get();

        return view('admin.facilities.index', compact('facilities'));
    }

    public function create()
    {
        return view('admin.facilities.form', ['facility' => new Facility(['slot_minutes' => 60, 'capacity' => 10,
            'cancel_deadline_hours' => 2, 'allowed_roles' => Roles::BOOKING, 'is_active' => true])]);
    }

    public function store(Request $request)
    {
        $facility = Facility::create($this->validated($request));
        $facility->units()->create(['name_tr' => 'Ana Alan', 'name_en' => 'Main Area']);
        foreach (range(1, 7) as $d) {
            $facility->hours()->create(['weekday' => $d, 'is_closed' => $d === 7, 'opens_at' => '08:00', 'closes_at' => '22:00']);
        }
        $facility->activities()->create(['name_tr' => 'Genel Kullanım', 'name_en' => 'General Use', 'units_required' => 1,
            'allowed_roles' => $facility->allowed_roles]);
        Audit::log('facility.created', $facility, $facility->only('name_tr', 'slot_minutes', 'capacity'));

        return redirect()->route('admin.facilities.edit', $facility)->with('success', __('Tesis oluşturuldu. Birimleri, aktiviteleri ve saatleri düzenleyin.'));
    }

    public function edit(Facility $facility)
    {
        $facility->load('units', 'activities', 'hours', 'rules', 'approvers');
        $candidates = User::where('is_active', true)->whereIn('base_role', [Roles::ADMIN_STAFF, Roles::ACADEMIC])->orderBy('name')->get();

        return view('admin.facilities.form', compact('facility', 'candidates'));
    }

    public function update(Request $request, Facility $facility)
    {
        $facility->fill($this->validated($request));
        $changes = $facility->getDirty();
        $facility->save();
        Audit::log('facility.updated', $facility, $changes);

        return back()->with('success', __('Tesis kaydedildi.'));
    }

    public function addUnit(Request $request, Facility $facility)
    {
        $data = $request->validate(['name_tr' => ['required', 'string', 'max:100'], 'name_en' => ['required', 'string', 'max:100']]);
        $unit = $facility->units()->create($data + ['sort' => $facility->units()->count()]);
        Audit::log('facility.unit_added', $facility, ['unit' => $unit->id, 'name' => $unit->name_tr]);

        return back()->with('success', __('Birim eklendi.'));
    }

    public function removeUnit(Facility $facility, FacilityUnit $unit)
    {
        abort_unless($unit->facility_id === $facility->id, 404);
        if ($facility->units()->count() <= 1) {
            return back()->withErrors(['unit' => __('Tesisin en az bir birimi olmalıdır.')]);
        }
        if (ReservationUnit::where('facility_unit_id', $unit->id)->where('is_active', true)->where('ends_at', '>', now())->exists()) {
            return back()->withErrors(['unit' => __('Bu birimde aktif rezervasyonlar var; önce onları iptal edin.')]);
        }
        Audit::log('facility.unit_removed', $facility, ['unit' => $unit->id, 'name' => $unit->name_tr]);
        $unit->delete();

        return back()->with('success', __('Birim silindi.'));
    }

    public function saveActivity(Request $request, Facility $facility)
    {
        $data = $request->validate([
            'id' => ['nullable', 'integer'],
            'name_tr' => ['required', 'string', 'max:100'],
            'name_en' => ['required', 'string', 'max:100'],
            'units_required' => ['required', 'integer', 'min:1', 'max:'.max(1, $facility->units()->count())],
            'is_team' => ['boolean'],
            'is_active' => ['boolean'],
            'allowed_roles' => ['required', 'array', 'min:1'],
            'allowed_roles.*' => [Rule::in(Roles::BOOKING)],
        ]);
        $activity = $data['id'] ? ActivityType::where('facility_id', $facility->id)->findOrFail($data['id']) : new ActivityType(['facility_id' => $facility->id]);
        $activity->fill(collect($data)->except('id')->all() + ['is_team' => false, 'is_active' => false]);
        $activity->is_team = $request->boolean('is_team');
        $activity->is_active = $request->boolean('is_active');
        $activity->save();
        Audit::log('facility.activity_saved', $facility, $activity->only('id', 'name_tr', 'units_required', 'is_team', 'allowed_roles', 'is_active'));

        return back()->with('success', __('Aktivite kaydedildi.'));
    }

    public function updateHours(Request $request, Facility $facility)
    {
        $data = $request->validate([
            'hours' => ['required', 'array'],
            'hours.*.is_closed' => ['nullable', 'boolean'],
            'hours.*.opens_at' => ['nullable', 'date_format:H:i'],
            'hours.*.closes_at' => ['nullable', 'date_format:H:i'],
        ]);
        foreach (range(1, 7) as $d) {
            $h = $data['hours'][$d] ?? [];
            $closed = (bool) ($h['is_closed'] ?? false);
            if (! $closed && (empty($h['opens_at']) || empty($h['closes_at']) || ($h['closes_at'] !== '00:00' && $h['closes_at'] <= $h['opens_at']))) {
                return back()->withErrors(['hours' => __('Her açık gün için geçerli açılış ve kapanış saati girin.')]);
            }
            $facility->hours()->updateOrCreate(['weekday' => $d], ['is_closed' => $closed, 'opens_at' => $h['opens_at'] ?? null, 'closes_at' => $h['closes_at'] ?? null]);
        }
        Audit::log('facility.hours_updated', $facility, $data['hours']);

        return back()->with('success', __('Çalışma saatleri kaydedildi.'));
    }

    public function updateRules(Request $request, Facility $facility)
    {
        $keys = array_merge(['default'], Roles::BOOKING);
        $rules = ['rules' => ['array']];
        foreach (FacilityRule::FIELDS as $f) {
            $rules["rules.*.$f"] = ['nullable', 'integer', 'min:0', 'max:10000'];
        }
        $data = $request->validate($rules);
        foreach ($keys as $key) {
            $values = collect($data['rules'][$key] ?? [])->only(FacilityRule::FIELDS)->map(fn ($v) => $v === null || $v === '' ? null : (int) $v);
            $role = $key === 'default' ? null : $key;
            if ($values->filter(fn ($v) => $v !== null)->isEmpty()) {
                $facility->rules()->where('role', $role)->when($role === null, fn ($q) => $q->whereNull('role'))->delete();

                continue;
            }
            $existing = $facility->rules()->when($role === null, fn ($q) => $q->whereNull('role'), fn ($q) => $q->where('role', $role))->first();
            ($existing ?? new FacilityRule(['facility_id' => $facility->id, 'role' => $role]))->fill($values->all())->save();
        }
        Audit::log('facility.rules_updated', $facility, $data['rules'] ?? []);

        return back()->with('success', __('Kurallar kaydedildi.'));
    }

    public function updateApprovers(Request $request, Facility $facility)
    {
        $data = $request->validate(['approvers' => ['array'], 'approvers.*' => ['integer', 'exists:users,id']]);
        $facility->approvers()->sync($data['approvers'] ?? []);
        Audit::log('facility.approvers_updated', $facility, ['approvers' => $data['approvers'] ?? []]);

        return back()->with('success', __('Onaycılar kaydedildi.'));
    }

    public function regenerateToken(Facility $facility)
    {
        $facility->checkin_token = Facility::newToken();
        $facility->save();
        Audit::log('facility.qr_regenerated', $facility);

        return back()->with('success', __('Yeni QR kodu oluşturuldu. Eski kod artık geçersiz; yenisini yazdırıp asın.'));
    }

    public function qr(Facility $facility)
    {
        $url = route('checkin.show', $facility->checkin_token);
        $svg = (new Writer(new ImageRenderer(new RendererStyle(320, 2), new SvgImageBackEnd)))->writeString($url);

        return view('admin.facilities.qr', compact('facility', 'svg', 'url'));
    }

    private function validated(Request $request): array
    {
        $data = $request->validate([
            'name_tr' => ['required', 'string', 'max:150'],
            'name_en' => ['required', 'string', 'max:150'],
            'description_tr' => ['nullable', 'string', 'max:1000'],
            'description_en' => ['nullable', 'string', 'max:1000'],
            'slot_minutes' => ['required', Rule::in([30, 60])],
            'capacity' => ['required', 'integer', 'min:1', 'max:1000'],
            'cancel_deadline_hours' => ['required', 'integer', 'min:0', 'max:168'],
            'allowed_roles' => ['required', 'array', 'min:1'],
            'allowed_roles.*' => [Rule::in(Roles::BOOKING)],
            'sort' => ['nullable', 'integer', 'min:0', 'max:1000'],
        ]);
        $data['group_allowed'] = $request->boolean('group_allowed');
        $data['participant_list_required'] = $request->boolean('participant_list_required') && $data['group_allowed'];
        $data['is_active'] = $request->boolean('is_active');
        $data['sort'] ??= 0;

        return $data;
    }
}
