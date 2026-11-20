@extends('layouts.app')
@section('title', __('Resmi Tatiller'))
@section('content')
<div class="page-head"><h1>{{ __('Resmi Tatiller') }} {{ $year }}</h1>
    <div class="actions"><a class="btn btn-sm" href="?year={{ $year - 1 }}">◀ {{ $year - 1 }}</a><a class="btn btn-sm" href="?year={{ $year + 1 }}">{{ $year + 1 }} ▶</a></div></div>
<div class="alert alert-warning small">{{ __('Dini bayram tarihleri her yıl değişir. Listeyi Diyanet takvimine göre kontrol edin.') }}</div>
<div class="card table-wrap"><table class="table">
    <thead><tr><th>{{ __('Tarih') }}</th><th>{{ __('Ad') }}</th><th>{{ __('Kapalı') }}</th><th></th></tr></thead>
    <tbody>@forelse ($holidays as $h)
        <tr><td class="nowrap">{{ $h->date->locale(app()->getLocale())->isoFormat('D MMMM YYYY, dddd') }}</td><td>{{ $h->name }}</td>
            <td>{{ $h->closed_from ? __(':t\'den itibaren', ['t' => substr($h->closed_from, 0, 5)]) : __('Tam gün') }}</td>
            <td><form method="post" action="{{ route('admin.holidays.destroy', $h) }}" data-confirm="{{ __('Silinsin mi?') }}">@csrf @method('delete')<button class="btn btn-sm btn-danger">{{ __('Sil') }}</button></form></td></tr>
    @empty<tr><td colspan="4" class="muted">{{ __('Kayıt yok.') }}</td></tr>@endforelse</tbody>
</table></div>
<form class="card" method="post" action="{{ route('admin.holidays.store') }}">@csrf
    <h2 class="mt-0">{{ __('Tatil ekle') }}</h2>
    <div class="form-row">
        <div class="field"><label for="h-d">{{ __('Tarih') }}</label><input id="h-d" type="date" name="date" required></div>
        <div class="field"><label for="h-tr">{{ __('Ad (TR)') }}</label><input id="h-tr" type="text" name="name_tr" required maxlength="150"></div>
        <div class="field"><label for="h-en">{{ __('Ad (EN)') }}</label><input id="h-en" type="text" name="name_en" required maxlength="150"></div>
        <div class="field"><label for="h-f">{{ __('Yarım gün: kapanış saati') }}</label><input id="h-f" type="time" name="closed_from"><div class="hint">{{ __('Boş = tam gün kapalı') }}</div></div>
    </div>
    <button class="btn btn-primary" type="submit">{{ __('Ekle') }}</button>
</form>
@endsection
