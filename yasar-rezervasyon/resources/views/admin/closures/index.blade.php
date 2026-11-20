@extends('layouts.app')
@section('title', __('Kapatmalar'))
@section('content')
<div class="page-head"><h1>{{ __('Kapatmalar') }}</h1></div>
<form class="card" method="post" action="{{ route('admin.closures.store') }}">@csrf
    <h2 class="mt-0">{{ __('Yeni kapatma') }}</h2>
    <p class="hint">{{ __('Bakım, turnuva, etkinlik vb. Çakışan rezervasyonlar iptal edilir ve kullanıcılara e-posta gider.') }}</p>
    <div class="form-row">
        <div class="field"><label for="c-f">{{ __('Tesis') }}</label><select id="c-f" name="facility_id"><option value="">{{ __('Tüm tesisler') }}</option>
            @foreach ($facilities as $f)<option value="{{ $f->id }}" @selected(old('facility_id') == $f->id)>{{ $f->name }}</option>@endforeach</select></div>
        <div class="field"><label for="c-s">{{ __('Başlangıç') }}</label><input id="c-s" type="datetime-local" name="starts_at" required value="{{ old('starts_at') }}"></div>
        <div class="field"><label for="c-e">{{ __('Bitiş') }}</label><input id="c-e" type="datetime-local" name="ends_at" required value="{{ old('ends_at') }}"></div>
    </div>
    <div class="field"><label for="c-r">{{ __('Açıklama') }}</label><input id="c-r" type="text" name="reason" required maxlength="255" value="{{ old('reason') }}"></div>
    @if (session('conflicts'))
        <div class="alert alert-warning">
            <strong>{{ __('Bu kapatma aşağıdaki rezervasyonlarla çakışıyor; kaydederseniz iptal edilecekler:') }}</strong>
            <ul>@foreach (session('conflicts') as $c)<li>{{ $c['code'] }} · {{ $c['facility'] }} · {{ $c['time'] }} · {{ $c['user'] }}</li>@endforeach</ul>
        </div>
        <input type="hidden" name="confirm" value="1">
        <button class="btn btn-danger" type="submit">{{ __('Rezervasyonları iptal et ve kaydet') }}</button>
    @else
        <button class="btn btn-primary" type="submit">{{ __('Kaydet') }}</button>
    @endif
</form>
<div class="card table-wrap"><table class="table">
    <thead><tr><th>{{ __('Tesis') }}</th><th>{{ __('Başlangıç') }}</th><th>{{ __('Bitiş') }}</th><th>{{ __('Açıklama') }}</th><th></th></tr></thead>
    <tbody>@forelse ($closures as $c)
        <tr><td>{{ $c->facility?->name ?? __('Tüm tesisler') }}</td><td>{{ $c->starts_at->format('d.m.Y H:i') }}</td><td>{{ $c->ends_at->format('d.m.Y H:i') }}</td><td>{{ $c->reason }}</td>
            <td><form method="post" action="{{ route('admin.closures.destroy', $c) }}" data-confirm="{{ __('Kapatma kaldırılsın mı? İptal edilen rezervasyonlar geri gelmez.') }}">@csrf @method('delete')<button class="btn btn-sm btn-danger">{{ __('Kaldır') }}</button></form></td></tr>
    @empty<tr><td colspan="5" class="muted">{{ __('Kayıt yok.') }}</td></tr>@endforelse</tbody>
</table></div>
@endsection
