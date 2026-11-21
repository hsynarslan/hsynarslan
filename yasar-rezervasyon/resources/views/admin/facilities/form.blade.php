@extends('layouts.app')
@section('title', $facility->exists ? $facility->name : __('Yeni tesis'))
@section('content')
@php
    $roles = \App\Support\Roles::bookingOptions();
    $days = [1 => 'Pazartesi', 2 => 'Salı', 3 => 'Çarşamba', 4 => 'Perşembe', 5 => 'Cuma', 6 => 'Cumartesi', 7 => 'Pazar'];
@endphp
<div class="page-head"><h1>{{ $facility->exists ? $facility->name : __('Yeni tesis') }}</h1>
    <a class="btn" href="{{ route('admin.facilities.index') }}">{{ __('Tesisler') }}</a></div>

<form class="card" method="post" action="{{ $facility->exists ? route('admin.facilities.update', $facility) : route('admin.facilities.store') }}">
    @csrf @if ($facility->exists) @method('put') @endif
    <h2 class="mt-0">{{ __('Genel ayarlar') }}</h2>
    <div class="form-row">
        <div class="field"><label for="name_tr">{{ __('Ad (TR)') }}</label><input id="name_tr" name="name_tr" type="text" required maxlength="150" value="{{ old('name_tr', $facility->name_tr) }}"></div>
        <div class="field"><label for="name_en">{{ __('Ad (EN)') }}</label><input id="name_en" name="name_en" type="text" required maxlength="150" value="{{ old('name_en', $facility->name_en) }}"></div>
    </div>
    <div class="form-row">
        <div class="field"><label for="description_tr">{{ __('Açıklama (TR)') }}</label><textarea id="description_tr" name="description_tr" maxlength="1000">{{ old('description_tr', $facility->description_tr) }}</textarea></div>
        <div class="field"><label for="description_en">{{ __('Açıklama (EN)') }}</label><textarea id="description_en" name="description_en" maxlength="1000">{{ old('description_en', $facility->description_en) }}</textarea></div>
    </div>
    <div class="form-row">
        <div class="field"><label for="slot_minutes">{{ __('Slot süresi') }}</label>
            <select id="slot_minutes" name="slot_minutes">@foreach ([30, 60] as $s)<option value="{{ $s }}" @selected(old('slot_minutes', $facility->slot_minutes) == $s)>{{ $s }} {{ __('dakika') }}</option>@endforeach</select></div>
        <div class="field"><label for="capacity">{{ __('Kapasite (kişi)') }}</label><input id="capacity" name="capacity" type="number" min="1" max="1000" required value="{{ old('capacity', $facility->capacity) }}"></div>
        <div class="field"><label for="cancel_deadline_hours">{{ __('İptal süresi (saat önce)') }}</label><input id="cancel_deadline_hours" name="cancel_deadline_hours" type="number" min="0" max="168" required value="{{ old('cancel_deadline_hours', $facility->cancel_deadline_hours) }}">
            <div class="hint">{{ __('Bu süreden sonraki iptaller geç iptal sayılır.') }}</div></div>
        <div class="field"><label for="checkin_mode">{{ __('QR giriş türü') }}</label>
            <select id="checkin_mode" name="checkin_mode">
                <option value="dynamic" @selected(old('checkin_mode', $facility->checkin_mode ?? 'dynamic') === 'dynamic')>{{ __('Dinamik (girişte tablet, önerilen)') }}</option>
                <option value="static" @selected(old('checkin_mode', $facility->checkin_mode) === 'static')>{{ __('Sabit (basılı QR)') }}</option>
            </select>
            <div class="hint">{{ __('Dinamik QR 30 saniyede bir değişir; fotoğrafı çekilip sonradan kullanılamaz.') }}</div></div>
        <div class="field"><label for="sort">{{ __('Sıra') }}</label><input id="sort" name="sort" type="number" min="0" value="{{ old('sort', $facility->sort ?? 0) }}"></div>
    </div>
    <label class="check"><input type="checkbox" name="group_allowed" value="1" data-group-toggle @checked(old('group_allowed', $facility->group_allowed))> {{ __('Grup rezervasyonuna açık') }}</label>
    <label class="check"><input type="checkbox" name="participant_list_required" value="1" data-list-toggle @checked(old('participant_list_required', $facility->participant_list_required))> {{ __('Katılımcı listesi zorunlu (LDAP\'tan seçilir, limitlerden düşer)') }}</label>
    <label class="check"><input type="checkbox" name="is_active" value="1" @checked(old('is_active', $facility->is_active))> {{ __('Aktif') }}</label>
    <div class="field mt"><span class="hint">{{ __('Bu tesisi rezerve edebilecek roller') }}</span>
        <div class="checks">@foreach ($roles as $k => $label)<label class="check"><input type="checkbox" name="allowed_roles[]" value="{{ $k }}" @checked(in_array($k, old('allowed_roles', $facility->allowed_roles ?? [])))> {{ $label }}</label>@endforeach</div></div>
    <button class="btn btn-primary" type="submit">{{ __('Kaydet') }}</button>
</form>

@if ($facility->exists)
<div class="grid grid-2">
    <div class="card">
        <h2 class="mt-0">{{ __('Alt birimler') }}</h2>
        <p class="hint">{{ __('Çakışma kontrolü birim düzeyinde yapılır. Ör. spor salonu: Pota A, Pota B.') }}</p>
        <table class="table"><tbody>
        @foreach ($facility->units as $unit)
            <tr><td>{{ $unit->name_tr }} / {{ $unit->name_en }}</td>
                <td class="right"><form method="post" action="{{ route('admin.facilities.units.destroy', [$facility, $unit]) }}" data-confirm="{{ __('Birim silinsin mi?') }}">@csrf @method('delete')<button class="btn btn-sm btn-danger" type="submit">{{ __('Sil') }}</button></form></td></tr>
        @endforeach
        </tbody></table>
        <form method="post" action="{{ route('admin.facilities.units.store', $facility) }}" class="form-row mt">@csrf
            <div class="field"><label for="u-tr">{{ __('Ad (TR)') }}</label><input id="u-tr" name="name_tr" type="text" required maxlength="100"></div>
            <div class="field"><label for="u-en">{{ __('Ad (EN)') }}</label><input id="u-en" name="name_en" type="text" required maxlength="100"></div>
            <div class="field"><label>&nbsp;</label><button class="btn" type="submit">+ {{ __('Birim ekle') }}</button></div>
        </form>
    </div>

    <div class="card">
        <h2 class="mt-0">{{ __('Çalışma saatleri') }}</h2>
        <form method="post" action="{{ route('admin.facilities.hours', $facility) }}">@csrf @method('put')
            <table class="table"><tbody>
            @foreach ($days as $d => $label)
                @php($h = $facility->hourFor($d))
                <tr><th>{{ __($label) }}</th>
                    <td><label class="check"><input type="checkbox" name="hours[{{ $d }}][is_closed]" value="1" @checked($h?->is_closed)> {{ __('Kapalı') }}</label></td>
                    <td><label class="sr-only" for="o{{ $d }}">{{ __('Açılış') }}</label><input id="o{{ $d }}" type="time" name="hours[{{ $d }}][opens_at]" value="{{ $h?->opens_at ? substr($h->opens_at, 0, 5) : '' }}"></td>
                    <td><label class="sr-only" for="c{{ $d }}">{{ __('Kapanış') }}</label><input id="c{{ $d }}" type="time" name="hours[{{ $d }}][closes_at]" value="{{ $h?->closes_at ? substr($h->closes_at, 0, 5) : '' }}"></td></tr>
            @endforeach
            </tbody></table>
            <button class="btn btn-primary" type="submit">{{ __('Saatleri kaydet') }}</button>
        </form>
    </div>
</div>

<div class="card">
    <h2 class="mt-0">{{ __('Aktiviteler') }}</h2>
    <p class="hint">{{ __('Gereken birim sayısı, aktivitenin kaç alanı kapatacağını belirler. Ör. Voleybol = 2 (tüm salon), Basketbol bireysel = 1, Basketbol takım = 2.') }}</p>
    @foreach ($facility->activities->push(new \App\Models\ActivityType(['allowed_roles' => $facility->allowed_roles, 'units_required' => 1, 'is_active' => true])) as $a)
        <form method="post" action="{{ route('admin.facilities.activities.save', $facility) }}" class="card">@csrf
            <input type="hidden" name="id" value="{{ $a->id }}">
            @unless ($a->exists)<strong>+ {{ __('Yeni aktivite') }}</strong>@endunless
            <div class="form-row">
                <div class="field"><label for="a{{ $a->id }}tr">{{ __('Ad (TR)') }}</label><input id="a{{ $a->id }}tr" name="name_tr" type="text" required maxlength="100" value="{{ $a->name_tr }}"></div>
                <div class="field"><label for="a{{ $a->id }}en">{{ __('Ad (EN)') }}</label><input id="a{{ $a->id }}en" name="name_en" type="text" required maxlength="100" value="{{ $a->name_en }}"></div>
                <div class="field"><label for="a{{ $a->id }}u">{{ __('Gereken birim') }}</label><input id="a{{ $a->id }}u" name="units_required" type="number" min="1" max="{{ $facility->units->count() }}" value="{{ $a->units_required }}"></div>
            </div>
            <div class="checks">
                <label class="check"><input type="checkbox" name="is_team" value="1" @checked($a->is_team)> {{ __('Takım aktivitesi (takım seçimi zorunlu)') }}</label>
                <label class="check"><input type="checkbox" name="is_active" value="1" @checked($a->is_active)> {{ __('Aktif') }}</label>
            </div>
            <div class="checks">@foreach ($roles as $k => $label)<label class="check"><input type="checkbox" name="allowed_roles[]" value="{{ $k }}" @checked(in_array($k, $a->allowed_roles ?? []))> {{ $label }}</label>@endforeach</div>
            <button class="btn btn-sm" type="submit">{{ __('Kaydet') }}</button>
        </form>
    @endforeach
</div>

<div class="grid grid-2">
    <div class="card">
        <h2 class="mt-0">{{ __('Kurallar') }}</h2>
        <p class="hint">{{ __('Boş bırakılan değer üst seviyeden alınır: rol → tesis varsayılanı → sistem ayarı. Antrenörler süre, günlük ve haftalık limitlerden muaftır.') }}</p>
        <form method="post" action="{{ route('admin.facilities.rules', $facility) }}">@csrf @method('put')
        <div class="table-wrap"><table class="table">
            <thead><tr><th></th><th>{{ __('En uzun süre (dk)') }}</th><th>{{ __('Günde') }}</th><th>{{ __('Haftada') }}</th><th>{{ __('En ileri (gün)') }}</th><th>{{ __('En geç (saat önce)') }}</th></tr></thead>
            <tbody>
            @foreach (['default' => __('Tesis varsayılanı')] + $roles as $key => $label)
                @php($rule = $facility->rules->first(fn ($r) => $r->role === ($key === 'default' ? null : $key)))
                <tr><th>{{ $label }}</th>
                    @foreach (\App\Models\FacilityRule::FIELDS as $field)
                        <td><input type="number" min="0" name="rules[{{ $key }}][{{ $field }}]" value="{{ $rule?->{$field} }}" placeholder="{{ $key === 'default' ? \App\Models\Setting::get('default_'.$field) : '' }}" aria-label="{{ $label }} {{ $field }}"></td>
                    @endforeach</tr>
            @endforeach
            </tbody>
        </table></div>
        <button class="btn btn-primary" type="submit">{{ __('Kuralları kaydet') }}</button>
        </form>
    </div>

    <div class="card">
        <h2 class="mt-0">{{ __('Onaycılar') }}</h2>
        <p class="hint">{{ __('Bu tesisin taleplerini onaylayabilecek kişiler. SKS Admin her zaman onaylayabilir.') }}</p>
        <form method="post" action="{{ route('admin.facilities.approvers', $facility) }}">@csrf @method('put')
            <label for="approvers" class="sr-only">{{ __('Onaycılar') }}</label>
            <select id="approvers" name="approvers[]" multiple size="8">
                @foreach ($candidates as $c)<option value="{{ $c->id }}" @selected($facility->approvers->contains($c))>{{ $c->name }} ({{ $c->number }})</option>@endforeach
            </select>
            <div class="hint">{{ __('Birden fazla seçmek için Ctrl/Cmd tuşunu basılı tutun. Listede yalnızca sisteme en az bir kez giriş yapmış ya da içe aktarılmış personel görünür.') }}</div>
            <button class="btn btn-primary mt" type="submit">{{ __('Onaycıları kaydet') }}</button>
        </form>
        <h2>{{ __('Salon görevlileri') }}</h2>
        <p class="hint">{{ __('Bu tesiste katılım ve ekipman işaretleyebilecek görevliler. Görevli yalnızca atandığı tesisleri görür.') }}</p>
        <form method="post" action="{{ route('admin.facilities.attendants', $facility) }}">@csrf @method('put')
            <label for="attendants" class="sr-only">{{ __('Salon görevlileri') }}</label>
            <select id="attendants" name="attendants[]" multiple size="5">
                @foreach ($attendantCandidates as $c)<option value="{{ $c->id }}" @selected($facility->attendants->contains($c))>{{ $c->name }} ({{ $c->number }})</option>@endforeach
            </select>
            <div class="hint">{{ __('Listede yalnızca Kullanıcılar sayfasında "Salon Görevlisi" rolü verilmiş kişiler görünür.') }}</div>
            <button class="btn btn-primary mt" type="submit">{{ __('Görevlileri kaydet') }}</button>
        </form>
        <h2>{{ __('QR kodu') }}</h2>
        <div class="actions">
            @if ($facility->usesDynamicQr())
                <a class="btn" href="{{ route('kiosk.show', $facility) }}">{{ __('Giriş tableti ekranını aç') }}</a>
            @else
                <a class="btn" href="{{ route('admin.facilities.qr', $facility) }}">{{ __('QR kodunu göster / yazdır') }}</a>
            @endif
            <form method="post" action="{{ route('admin.facilities.token', $facility) }}" data-confirm="{{ __('Eski QR kodu geçersiz olacak. Devam edilsin mi?') }}">@csrf<button class="btn btn-danger" type="submit">{{ __('QR kodunu yenile') }}</button></form>
        </div>
    </div>
</div>
@endif
@endsection
