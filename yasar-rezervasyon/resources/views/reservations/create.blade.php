@extends('layouts.app')
@section('title', __('Yeni rezervasyon'))
@section('content')
<div class="page-head"><h1>{{ __('Yeni rezervasyon talebi') }}</h1>
    <a class="btn" href="{{ route('calendar', ['facility' => $facility->id, 'date' => $date->toDateString()]) }}">{{ __('Takvime dön') }}</a></div>

<div class="grid grid-2">
<div class="card">
    <form method="get" action="{{ route('reservations.create') }}" class="form-row">
        <div class="field">
            <label for="f-facility">{{ __('Tesis') }}</label>
            <select id="f-facility" name="facility" data-autosubmit>
                @foreach ($facilities as $f)<option value="{{ $f->id }}" @selected($f->id === $facility->id)>{{ $f->name }}</option>@endforeach
            </select>
        </div>
        <div class="field">
            <label for="f-date">{{ __('Tarih') }}</label>
            <input id="f-date" type="date" name="date" value="{{ $date->toDateString() }}" min="{{ today()->toDateString() }}" data-autosubmit>
        </div>
        <noscript><button class="btn" type="submit">{{ __('Güncelle') }}</button></noscript>
    </form>

    @if ($times->isEmpty())
        <div class="alert alert-info">{{ __('Tesis bu tarihte kapalı.') }}</div>
    @elseif ($activities->isEmpty())
        <div class="alert alert-info">{{ __('Bu tesiste rolünüze açık bir aktivite yok.') }}</div>
    @else
    <form method="post" action="{{ route('reservations.store') }}">
        @csrf
        <input type="hidden" name="facility_id" value="{{ $facility->id }}">
        <input type="hidden" name="date" value="{{ $date->toDateString() }}">

        <div class="field">
            <label for="activity">{{ __('Aktivite') }}</label>
            <select id="activity" name="activity_type_id" required>
                @foreach ($activities as $a)
                    <option value="{{ $a->id }}" @selected(old('activity_type_id') == $a->id)>{{ $a->name }} — {{ $a->units_required >= $facility->units->count() && $facility->units->count() > 1 ? __('tüm alan') : trans_choice(':count birim', $a->units_required) }}{{ $a->is_team ? ' · '.__('takım') : '' }}</option>
                @endforeach
            </select>
            @error('activity_type_id')<div class="error">{{ $message }}</div>@enderror
        </div>

        <div class="form-row">
            <div class="field">
                <label for="start">{{ __('Başlangıç') }}</label>
                <select id="start" name="start" required data-start>
                    @foreach ($times as $t)<option value="{{ $t }}" @selected($start === $t)>{{ $t }}</option>@endforeach
                </select>
            </div>
            <div class="field">
                <label for="end">{{ __('Bitiş') }}</label>
                <select id="end" name="end" required data-end>
                    @foreach ($endTimes as $t)<option value="{{ $t }}" @selected(old('end') === $t)>{{ $t }}</option>@endforeach
                </select>
            </div>
        </div>
        @error('start')<div class="error">{{ $message }}</div>@enderror
        @error('end')<div class="error">{{ $message }}</div>@enderror
        @error('date')<div class="error">{{ $message }}</div>@enderror

        @if ($facility->units->count() > 1)
            <div class="field">
                <label for="unit">{{ __('Tercih edilen alan') }}</label>
                <select id="unit" name="unit_id">
                    <option value="">{{ __('Fark etmez (sistem seçer)') }}</option>
                    @foreach ($facility->units as $unit)<option value="{{ $unit->id }}" @selected($unitId == $unit->id)>{{ $unit->name }}</option>@endforeach
                </select>
                <div class="hint">{{ __('Birden fazla alan gerektiren aktivitelerde (ör. voleybol) tüm alanlar ayrılır.') }}</div>
            </div>
        @endif

        @if ($activities->contains('is_team', true))
            <div class="field">
                <label for="team">{{ __('Takım') }}</label>
                <select id="team" name="team_id">
                    <option value="">—</option>
                    @foreach ($teams as $t)<option value="{{ $t->id }}" @selected(old('team_id') == $t->id)>{{ $t->name }}</option>@endforeach
                </select>
                <div class="hint">{{ __('Takım aktivitelerinde zorunludur.') }}</div>
                @error('team_id')<div class="error">{{ $message }}</div>@enderror
            </div>
        @endif

        @if ($facility->group_allowed && $facility->participant_list_required)
            <div class="field">
                <label for="participants">{{ __('Katılımcılar (öğrenci / personel numaraları)') }}</label>
                <textarea id="participants" name="participant_numbers" placeholder="20231046&#10;20231047">{{ old('participant_numbers') }}</textarea>
                <div class="hint">{{ __('Her satıra bir numara. Katılımcılara e-posta gider; bu rezervasyon onların günlük ve haftalık limitinden de düşer. Kapasite: :c kişi (siz dahil).', ['c' => $facility->capacity]) }}</div>
                @error('participants')<div class="error">{{ $message }}</div>@enderror
                @error('participant_numbers')<div class="error">{{ $message }}</div>@enderror
            </div>
        @endif
        @if (($facility->group_allowed && ! $facility->participant_list_required) || $activities->contains('is_team', true))
            <div class="field">
                <label for="count">{{ __('Kişi sayısı') }}</label>
                <input id="count" type="number" name="participant_count" min="1" max="{{ $facility->capacity }}" value="{{ old('participant_count', 1) }}">
                <div class="hint">{{ __('Kapasite: :c kişi', ['c' => $facility->capacity]) }}</div>
                @error('participant_count')<div class="error">{{ $message }}</div>@enderror
            </div>
        @endif

        @if ($facility->equipment->where('is_active', true)->isNotEmpty())
            <h3>{{ __('Ekipman') }}</h3>
            <div class="form-row">
                @foreach ($facility->equipment->where('is_active', true) as $eq)
                    <div class="field">
                        <label for="eq-{{ $eq->id }}">{{ $eq->name }}</label>
                        <input id="eq-{{ $eq->id }}" type="number" min="0" max="{{ $eq->total_stock }}" name="equipment[{{ $eq->id }}]" value="{{ old('equipment.'.$eq->id, 0) }}">
                    </div>
                @endforeach
            </div>
            @error('equipment')<div class="error">{{ $message }}</div>@enderror
        @endif

        <div class="field">
            <label for="notes">{{ __('Not (isteğe bağlı)') }}</label>
            <textarea id="notes" name="notes" maxlength="500">{{ old('notes') }}</textarea>
        </div>

        <button class="btn btn-primary" type="submit">{{ __('Talep gönder') }}</button>
    </form>
    @endif
</div>

<div class="card">
    <h2 class="mt-0">{{ $facility->name }}</h2>
    @if ($facility->localized('description'))<p>{{ $facility->localized('description') }}</p>@endif
    <ul class="small">
        <li>{{ __('Talepler SKS onayından sonra kesinleşir. Başlangıç saatine kadar onaylanmayan talepler otomatik iptal edilir.') }}</li>
        <li>{{ __('Seçtiğiniz saat, talebiniz beklemedeyken başkalarına kapalıdır.') }}</li>
        <li>{{ __('İptal: başlangıçtan en geç :h saat önce. Daha sonraki iptaller geç iptal sayılır.', ['h' => $facility->cancel_deadline_hours]) }}</li>
        <li>{{ __('Tesise girişte QR kodu okutun; ilk :m dakikada giriş yapılmazsa "gelmedi" sayılır.', ['m' => \App\Models\Setting::get('noshow_grace_minutes')]) }}</li>
    </ul>
</div>
</div>
@endsection
