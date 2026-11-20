@extends('layouts.app')
@section('title', __('Günlük Liste'))
@section('content')
<div class="page-head"><div><h1>{{ __('Günlük Liste') }}</h1><p class="lead">{{ __('Katılımı işaretleyin, ekipmanı teslim edin ve teslim alın.') }}</p></div></div>
<form method="get" class="card form-row">
    <div class="field"><label for="a-f">{{ __('Tesis') }}</label>
        <select id="a-f" name="facility" data-autosubmit>@foreach ($facilities as $f)<option value="{{ $f->id }}" @selected($facility?->id === $f->id)>{{ $f->name }}</option>@endforeach</select></div>
    <div class="field"><label for="a-d">{{ __('Tarih') }}</label><input id="a-d" type="date" name="date" value="{{ $date->toDateString() }}" data-autosubmit></div>
    <noscript><button class="btn">{{ __('Göster') }}</button></noscript>
</form>
@forelse ($reservations as $r)
    <article class="card">
        <div class="card-head">
            <div>
                <div class="actions">
                    <span class="badge badge-info badge-plain">@icon('clock', 14) {{ $r->starts_at->format('H:i') }}–{{ $r->ends_at->format('H:i') }}</span>
                    <strong>{{ $r->title() }}</strong>
                    <span class="badge">{{ $r->units->map->name->join(', ') }}</span>
                    @if ($r->attendance)<span class="badge badge-{{ $r->attendance }}">{{ $r->attendance === 'attended' ? __('Geldi') : __('Gelmedi') }}</span>@endif
                </div>
                @unless ($r->isBlock())
                    <div class="small">{{ $r->user?->name }} ({{ $r->user?->number }}) · {{ trans_choice(':count kişi', $r->participant_count) }}@if ($r->team) · {{ $r->team->name }}@endif</div>
                    @if ($r->checkins->isNotEmpty())<div class="small muted">{{ __('Check-in') }}: {{ $r->checkins->map(fn ($c) => ($c->user?->name ?? '—').' '.$c->checked_in_at->format('H:i').' ('.$c->method.')')->join(', ') }}</div>@endif
                    @if ($r->participantUsers->isNotEmpty())<div class="small muted">{{ __('Katılımcılar') }}: {{ $r->participantUsers->pluck('name')->join(', ') }}</div>@endif
                @endunless
            </div>
            @unless ($r->isBlock())
            <div class="actions">
                <form method="post" action="{{ route('attendant.attendance', $r) }}">@csrf<input type="hidden" name="attendance" value="attended">
                    <button class="btn btn-sm btn-success" type="submit" @disabled($r->attendance === 'attended')>{{ __('Geldi') }}</button></form>
                <form method="post" action="{{ route('attendant.attendance', $r) }}">@csrf<input type="hidden" name="attendance" value="no_show">
                    <button class="btn btn-sm btn-danger" type="submit" @disabled($r->attendance === 'no_show')>{{ __('Gelmedi') }}</button></form>
            </div>
            @endunless
        </div>
        @foreach ($r->equipmentItems as $item)
            <div class="actions mt small form-section">
                <strong>{{ $item->quantity }} × {{ $item->equipment->name }}</strong>
                @if (! $item->delivered_at)
                    <form method="post" action="{{ route('attendant.equipment', $item) }}">@csrf<input type="hidden" name="action" value="deliver">
                        <button class="btn btn-sm" type="submit">{{ __('Teslim et') }}</button></form>
                @elseif (! $item->returned_at)
                    <span class="badge badge-info">{{ __('Teslim edildi') }} {{ $item->delivered_at->format('H:i') }}</span>
                    <form method="post" action="{{ route('attendant.equipment', $item) }}" class="actions">@csrf<input type="hidden" name="action" value="return">
                        <label class="sr-only" for="cond-{{ $item->id }}">{{ __('Durum') }}</label>
                        <select id="cond-{{ $item->id }}" name="condition"><option value="ok">{{ __('Sağlam') }}</option><option value="damaged">{{ __('Hasarlı') }}</option><option value="missing">{{ __('Kayıp') }}</option></select>
                        <input type="text" name="note" placeholder="{{ __('Not') }}" maxlength="500">
                        <button class="btn btn-sm" type="submit">{{ __('Teslim al') }}</button>
                    </form>
                @else
                    <span class="badge badge-{{ $item->return_condition === 'ok' ? 'ok' : 'danger' }}">{{ __('İade') }}: {{ $item->conditionLabel() }} {{ $item->returned_at->format('H:i') }}</span>
                    @if ($item->note)<span class="muted">{{ $item->note }}</span>@endif
                @endif
            </div>
        @endforeach
    </article>
@empty
    <div class="card empty">@icon('clipboard', 36)<p>{{ __('Bu gün için onaylı rezervasyon yok.') }}</p></div>
@endforelse
@endsection
