@extends('layouts.app')
@section('title', __('Takvim'))
@section('content')
@php
    use App\Services\Availability;
    $nav = fn ($d, $v = null) => route('calendar', ['facility' => $facility->id, 'date' => $d->toDateString(), 'view' => $v ?? $view]);
    $step = $view === 'week' ? 7 : 1;
@endphp
<div class="page-head">
    <div>
        <h1>{{ __('Takvim') }}</h1>
        <p class="muted mt-0">{{ __('Boş bir hücreye tıklayarak rezervasyon talebi oluşturun.') }}</p>
    </div>
    @if ($canBook)
        <a class="btn btn-primary" href="{{ route('reservations.create', ['facility' => $facility->id, 'date' => $date->toDateString()]) }}">+ {{ __('Yeni rezervasyon') }}</a>
    @endif
</div>

<nav class="facility-tabs" aria-label="{{ __('Tesisler') }}">
    @foreach ($facilities as $f)
        <a href="{{ route('calendar', ['facility' => $f->id, 'date' => $date->toDateString(), 'view' => $view]) }}" @class(['active' => $f->id === $facility->id])>{{ $f->name }}</a>
    @endforeach
</nav>

<div class="card">
    <div class="cal-toolbar">
        <div class="group">
            <a class="btn btn-sm" href="{{ $nav($date->copy()->subDays($step)) }}" aria-label="{{ __('Önceki') }}">◀</a>
            <span class="cal-date">
                @if ($view === 'week')
                    {{ $days->first()->locale(app()->getLocale())->isoFormat('D MMM') }} – {{ $days->last()->locale(app()->getLocale())->isoFormat('D MMM YYYY') }}
                @else
                    {{ $date->locale(app()->getLocale())->isoFormat('dddd, D MMMM YYYY') }}
                @endif
            </span>
            <a class="btn btn-sm" href="{{ $nav($date->copy()->addDays($step)) }}" aria-label="{{ __('Sonraki') }}">▶</a>
            <a class="btn btn-sm" href="{{ $nav(today()) }}">{{ __('Bugün') }}</a>
            <form method="get" action="{{ route('calendar') }}" class="inline-form">
                <input type="hidden" name="facility" value="{{ $facility->id }}"><input type="hidden" name="view" value="{{ $view }}">
                <label class="sr-only" for="cal-date">{{ __('Tarih') }}</label>
                <input id="cal-date" type="date" name="date" value="{{ $date->toDateString() }}" data-autosubmit>
            </form>
        </div>
        <div class="group">
            <a @class(['btn btn-sm', 'btn-primary' => $view === 'day']) href="{{ $nav($date, 'day') }}">{{ __('Gün') }}</a>
            <a @class(['btn btn-sm', 'btn-primary' => $view === 'week']) href="{{ $nav($date, 'week') }}">{{ __('Hafta') }}</a>
        </div>
    </div>

    @if ($facility->localized('description'))<p class="muted small">{{ $facility->localized('description') }}</p>@endif
    <p class="small muted">{{ __('Kapasite') }}: {{ $facility->capacity }} · {{ __('Slot') }}: {{ $facility->slot_minutes }} {{ __('dk') }}
        · {{ $facility->group_allowed ? __('Grup rezervasyonuna açık') : __('Yalnızca bireysel') }}</p>

    @if ($slotMinutes->isEmpty())
        <div class="alert alert-info">{{ __('Tesis bu tarihlerde kapalı.') }}</div>
    @else
    <div class="table-wrap">
        <table @class(["calendar", "week" => $view === "week"])>
            <thead>
            <tr>
                <th class="time">{{ __('Saat') }}</th>
                @if ($view === 'day')
                    @foreach ($facility->units as $unit)<th>{{ $unit->name }}</th>@endforeach
                @else
                    @foreach ($days as $d)
                        <th @class(['today' => $d->isToday()])><a href="{{ $nav($d, 'day') }}">{{ $d->locale(app()->getLocale())->isoFormat('ddd D') }}</a></th>
                    @endforeach
                @endif
            </tr>
            </thead>
            <tbody>
            @foreach ($slotMinutes as $m)
                <tr>
                    <td class="time">{{ Availability::minutesToTime($m) }}</td>
                    @if ($view === 'day')
                        @foreach ($facility->units as $unit)
                            @php($c = $cell($date, $m, $unit))
                            <td>
                                @if ($c['state'] === 'free' && $canBook)
                                    <a class="slot free" href="{{ route('reservations.create', ['facility' => $facility->id, 'date' => $date->toDateString(), 'start' => Availability::minutesToTime($m), 'unit' => $unit->id]) }}" aria-label="{{ $unit->name }} {{ Availability::minutesToTime($m) }} {{ __('boş, rezerve et') }}">{{ __('Boş') }}</a>
                                @elseif (isset($c['lock']))
                                    @php($r = $c['lock']->reservation)
                                    <div @class(['slot', $c['state'], 'block' => $r->isBlock(), 'mine' => $c['mine'] && ! $r->isBlock()]) title="{{ $r->title() }}">
                                        @if ($r->isBlock()) {{ $r->title() }}
                                        @elseif ($c['mine']) {{ __('Benim') }} · {{ $r->statusLabel() }}
                                        @elseif ($canSeeNames) {{ $r->user?->name }}@if ($r->team) · {{ $r->team->name }}@endif
                                        @else {{ $r->statusLabel() }}
                                        @endif
                                    </div>
                                @else
                                    <div class="slot {{ $c['state'] }}">{{ $c['label'] ?? '' }}</div>
                                @endif
                            </td>
                        @endforeach
                    @else
                        @foreach ($days as $d)
                            @php($c = $cell($d, $m, null))
                            <td>
                                @if (in_array($c['state'], ['free', 'partial']) && $canBook)
                                    <a class="slot {{ $c['state'] }}" href="{{ route('reservations.create', ['facility' => $facility->id, 'date' => $d->toDateString(), 'start' => Availability::minutesToTime($m)]) }}">{{ $c['label'] }}</a>
                                @else
                                    <div class="slot {{ $c['state'] }}">{{ $c['state'] === 'busy' ? __('Dolu') : $c['label'] }}</div>
                                @endif
                            </td>
                        @endforeach
                    @endif
                </tr>
            @endforeach
            </tbody>
        </table>
    </div>
    @endif
    <div class="legend">
        <span class="l-free">{{ __('Boş') }}</span><span class="l-pending">{{ __('Beklemede') }}</span>
        <span class="l-approved">{{ __('Onaylı') }}</span><span class="l-mine">{{ __('Benim') }}</span>
        <span class="l-block">{{ __('Sabit blok / antrenman') }}</span><span class="l-closed">{{ __('Kapalı / geçmiş') }}</span>
    </div>
</div>
@endsection
