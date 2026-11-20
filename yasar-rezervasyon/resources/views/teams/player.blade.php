@extends('layouts.app')
@section('title', $player->exists ? $player->user->name : __('Oyuncu ekle'))
@section('content')
@php($readonly = $player->exists && $player->left_on)
<div class="page-head">
    <h1>{{ $player->exists ? $player->user->name : __('Oyuncu ekle') }}</h1>
    <a class="btn" href="{{ route('teams.show', $team) }}">{{ $team->name }}</a>
</div>
@if ($readonly)
    <div class="alert alert-info">{{ __('Eski oyuncu. Hassas bilgileri :date tarihinde silindi.', ['date' => $player->sensitive_purged_at?->format('d.m.Y')]) }}</div>
@endif
<form class="card" method="post" enctype="multipart/form-data" action="{{ $player->exists ? route('teams.players.update', [$team, $player]) : route('teams.players.store', $team) }}">
    @csrf
    @if ($player->exists) @method('put') @endif
    <fieldset @disabled($readonly)>
    @if ($player->exists)
        <div class="actions mb">
            @if ($player->photo_path)<img class="photo" src="{{ route('teams.players.photo', [$team, $player]) }}" alt="{{ $player->user->name }}">@endif
            <div><strong>{{ $player->user->name }}</strong><div class="muted small">{{ $player->user->number }} · {{ $player->user->email }} · {{ $player->user->department }}</div></div>
        </div>
    @else
        <div class="field">
            <label for="student_number">{{ __('Öğrenci numarası') }}</label>
            <input id="student_number" name="student_number" type="text" value="{{ old('student_number') }}" required maxlength="50">
            <div class="hint">{{ __('Ad, e-posta ve bölüm LDAP\'tan otomatik alınır. Yalnızca öğrenciler eklenebilir.') }}</div>
        </div>
    @endif
    <div class="form-row">
        <div class="field"><label for="jersey_no">{{ __('Forma no') }}</label><input id="jersey_no" name="jersey_no" type="text" maxlength="5" value="{{ old('jersey_no', $player->jersey_no) }}"></div>
        <div class="field"><label for="position">{{ __('Mevki') }}</label><input id="position" name="position" type="text" maxlength="50" value="{{ old('position', $player->position) }}"></div>
        <div class="field"><label for="joined_on">{{ __('Takıma katılış') }}</label><input id="joined_on" name="joined_on" type="date" value="{{ old('joined_on', $player->joined_on?->toDateString() ?? today()->toDateString()) }}"></div>
    </div>
    <h3>{{ __('Kişisel ve sağlık bilgileri') }} <span class="badge badge-info">{{ __('Şifreli saklanır') }}</span></h3>
    <div class="form-row">
        <div class="field"><label for="birth_date">{{ __('Doğum tarihi') }}</label><input id="birth_date" name="birth_date" type="date" value="{{ old('birth_date', $player->birth_date) }}"></div>
        <div class="field"><label for="phone">{{ __('Telefon') }}</label><input id="phone" name="phone" type="text" maxlength="30" value="{{ old('phone', $player->phone) }}"></div>
        <div class="field"><label for="license_no">{{ __('Lisans no') }}</label><input id="license_no" name="license_no" type="text" maxlength="50" value="{{ old('license_no', $player->license_no) }}"></div>
    </div>
    <div class="form-row">
        <div class="field"><label for="health_report_date">{{ __('Sağlık raporu tarihi') }}</label><input id="health_report_date" name="health_report_date" type="date" value="{{ old('health_report_date', $player->health_report_date) }}"></div>
        <div class="field"><label for="health_report_expires_on">{{ __('Sağlık raporu geçerlilik bitişi') }}</label><input id="health_report_expires_on" name="health_report_expires_on" type="date" value="{{ old('health_report_expires_on', $player->health_report_expires_on) }}"></div>
    </div>
    <div class="form-row">
        <div class="field"><label for="emergency_contact_name">{{ __('Acil durumda aranacak kişi') }}</label><input id="emergency_contact_name" name="emergency_contact_name" type="text" maxlength="100" value="{{ old('emergency_contact_name', $player->emergency_contact_name) }}"></div>
        <div class="field"><label for="emergency_contact_phone">{{ __('Acil durum telefonu') }}</label><input id="emergency_contact_phone" name="emergency_contact_phone" type="text" maxlength="30" value="{{ old('emergency_contact_phone', $player->emergency_contact_phone) }}"></div>
    </div>
    <div class="field"><label for="photo">{{ __('Fotoğraf (JPG/PNG, en fazla 2 MB)') }}</label><input id="photo" name="photo" type="file" accept="image/jpeg,image/png"></div>
    <div class="field"><label for="notes">{{ __('Notlar') }}</label><textarea id="notes" name="notes" maxlength="1000">{{ old('notes', $player->notes) }}</textarea></div>
    @unless ($readonly)<button class="btn btn-primary" type="submit">{{ __('Kaydet') }}</button>@endunless
    </fieldset>
</form>
@if ($player->exists && ! $readonly)
    <form method="post" action="{{ route('teams.players.remove', [$team, $player]) }}" data-confirm="{{ __('Oyuncu takımdan çıkarılacak ve sağlık/iletişim bilgileri KALICI olarak silinecek. Emin misiniz?') }}">
        @csrf @method('delete')
        <button class="btn btn-danger" type="submit">{{ __('Takımdan çıkar') }}</button>
    </form>
@endif
@endsection
