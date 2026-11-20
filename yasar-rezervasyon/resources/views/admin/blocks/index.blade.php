@extends('layouts.app')
@section('title', __('Sabit Bloklar'))
@section('content')
@php($days = [1 => 'Pzt', 2 => 'Sal', 3 => 'Çar', 4 => 'Per', 5 => 'Cum', 6 => 'Cmt', 7 => 'Paz'])
<div class="page-head"><div><h1>{{ __('Sabit Bloklar') }}</h1><p class="muted mt-0">{{ __('Takım antrenmanları ve dersler gibi tekrarlayan rezervasyonlar. Onay gerektirmez.') }}</p></div></div>
<div class="card table-wrap"><table class="table">
    <thead><tr><th>{{ __('Başlık') }}</th><th>{{ __('Tesis') }}</th><th>{{ __('Günler') }}</th><th>{{ __('Saat') }}</th><th>{{ __('Tarih aralığı') }}</th><th></th></tr></thead>
    <tbody>@forelse ($blocks as $b)
        <tr><td><strong>{{ $b->title }}</strong>@if ($b->team)<div class="small muted">{{ $b->team->name }}</div>@endif</td><td>{{ $b->facility->name }}</td>
            <td>{{ collect($b->weekdays)->map(fn ($d) => __($days[$d]))->join(', ') }}</td><td class="nowrap">{{ substr($b->start_time, 0, 5) }}–{{ substr($b->end_time, 0, 5) }}</td>
            <td class="nowrap">{{ $b->starts_on->format('d.m.Y') }} – {{ $b->ends_on->format('d.m.Y') }}</td>
            <td><form method="post" action="{{ route('admin.blocks.destroy', $b) }}" data-confirm="{{ __('Blok kaldırılsın mı? Gelecekteki oturumlar iptal edilir.') }}">@csrf @method('delete')<button class="btn btn-sm btn-danger">{{ __('Kaldır') }}</button></form></td></tr>
    @empty<tr><td colspan="6" class="muted">{{ __('Kayıt yok.') }}</td></tr>@endforelse</tbody>
</table></div>

@if ($facility)
<form class="card" method="get"><div class="field"><label for="b-f">{{ __('Tesis') }}</label>
    <select id="b-f" name="facility" data-autosubmit>@foreach ($facilities as $f)<option value="{{ $f->id }}" @selected($f->id === $facility->id)>{{ $f->name }}</option>@endforeach</select></div></form>

<form class="card" method="post" action="{{ route('admin.blocks.store') }}">@csrf
    <h2 class="mt-0">{{ __('Yeni sabit blok') }} · {{ $facility->name }}</h2>
    <input type="hidden" name="facility_id" value="{{ $facility->id }}">
    <div class="form-row">
        <div class="field"><label for="b-t">{{ __('Başlık') }}</label><input id="b-t" type="text" name="title" required maxlength="150" value="{{ old('title') }}" placeholder="{{ __('Erkek Basketbol Takımı Antrenmanı') }}"></div>
        <div class="field"><label for="b-a">{{ __('Aktivite') }}</label><select id="b-a" name="activity_type_id">@foreach ($facility->activities as $a)<option value="{{ $a->id }}" @selected(old('activity_type_id') == $a->id)>{{ $a->name }}</option>@endforeach</select></div>
        <div class="field"><label for="b-team">{{ __('Takım (isteğe bağlı)') }}</label><select id="b-team" name="team_id"><option value="">—</option>@foreach ($teams as $t)<option value="{{ $t->id }}" @selected(old('team_id') == $t->id)>{{ $t->name }}</option>@endforeach</select></div>
    </div>
    <div class="field"><span class="hint">{{ __('Alanlar') }}</span><div class="checks">@foreach ($facility->units as $u)<label class="check"><input type="checkbox" name="unit_ids[]" value="{{ $u->id }}" @checked(in_array($u->id, old('unit_ids', $facility->units->pluck('id')->all())))> {{ $u->name }}</label>@endforeach</div></div>
    <div class="field"><span class="hint">{{ __('Günler') }}</span><div class="checks">@foreach ($days as $d => $l)<label class="check"><input type="checkbox" name="weekdays[]" value="{{ $d }}" @checked(in_array($d, old('weekdays', [])))> {{ __($l) }}</label>@endforeach</div></div>
    <div class="form-row">
        <div class="field"><label for="b-st">{{ __('Başlangıç saati') }}</label><input id="b-st" type="time" name="start_time" required value="{{ old('start_time') }}"></div>
        <div class="field"><label for="b-et">{{ __('Bitiş saati') }}</label><input id="b-et" type="time" name="end_time" required value="{{ old('end_time') }}"></div>
        <div class="field"><label for="b-sd">{{ __('İlk tarih') }}</label><input id="b-sd" type="date" name="starts_on" required value="{{ old('starts_on') }}"></div>
        <div class="field"><label for="b-ed">{{ __('Son tarih') }}</label><input id="b-ed" type="date" name="ends_on" required value="{{ old('ends_on') }}"></div>
    </div>
    @if ($p = session('preview'))
        <div class="alert alert-info">
            <strong>{{ __('Önizleme: :n oturum oluşturulacak.', ['n' => $p['count']]) }}</strong>
            @if ($p['skipped'])<div>{{ __('Atlanan günler') }}: @foreach ($p['skipped'] as $s){{ \Illuminate\Support\Carbon::parse($s['date'])->format('d.m.Y') }} ({{ $s['reason'] }})@if (! $loop->last), @endif @endforeach</div>@endif
        </div>
        @if ($p['conflicts'])
            <div class="alert alert-warning"><strong>{{ __('Çakışan rezervasyonlar iptal edilecek:') }}</strong>
                <ul>@foreach ($p['conflicts'] as $c)<li>{{ $c['code'] }} · {{ $c['time'] }} · {{ $c['user'] }}@if ($c['block']) ({{ __('sabit blok') }})@endif</li>@endforeach</ul></div>
            <div class="field"><label for="b-cr">{{ __('İptal gerekçesi (kullanıcılara gönderilir)') }}</label><input id="b-cr" type="text" name="cancel_reason" maxlength="255" value="{{ old('cancel_reason') }}"></div>
        @endif
        <input type="hidden" name="confirm" value="1">
        <button class="btn btn-primary" type="submit">{{ __('Onayla ve oluştur') }}</button>
    @else
        <button class="btn btn-primary" type="submit">{{ __('Önizle') }}</button>
    @endif
</form>
@endif
@endsection
