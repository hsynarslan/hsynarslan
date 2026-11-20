@extends('layouts.app')
@section('title', __('Takvim'))
@section('content')
@php
    use App\Services\Availability;
    use App\Support\Icon;
    $loc = app()->getLocale();
    $nav = fn ($d, $v = null) => route('calendar', ['facility' => $facility->id, 'date' => $d->toDateString(), 'view' => $v ?? $view]);
    $step = $view === 'week' ? 7 : 1;
    $nowMin = now()->hour * 60 + now()->minute;
@endphp

<div class="page-head">
    <div>
        <span class="eyebrow">{{ now()->locale($loc)->isoFormat('dddd, D MMMM') }}</span>
        <h1>{{ __('Merhaba, :name', ['name' => explode(' ', auth()->user()->name)[0]]) }}</h1>
        <p class="lead">{{ __('Boş bir alana tıklayarak rezervasyon talebi oluşturun.') }}</p>
    </div>
    @if ($canBook)
        <a class="btn btn-primary" href="{{ route('reservations.create', ['facility' => $facility->id, 'date' => $date->toDateString()]) }}">@icon('plus') {{ __('Yeni rezervasyon') }}</a>
    @endif
</div>

@if ($next)
    <a class="next-card" href="{{ route('reservations.index') }}">
        <span class="date-chip"><b>{{ $next->starts_at->format('d') }}</b><small>{{ $next->starts_at->locale($loc)->isoFormat('MMM') }}</small></span>
        <span class="next-text">
            <small>{{ __('Sıradaki rezervasyonunuz') }}</small>
            <strong>{{ $next->facility->name }} · {{ $next->title() }}</strong>
            <span class="muted">{{ $next->starts_at->locale($loc)->isoFormat('dddd') }} {{ $next->starts_at->format('H:i') }}–{{ $next->ends_at->format('H:i') }} · {{ $next->units->map->name->join(', ') }}</span>
        </span>
        <span class="badge badge-{{ $next->status }}">{{ $next->statusLabel() }}</span>
    </a>
@endif

<nav class="fac-tabs" aria-label="{{ __('Tesisler') }}">
    @foreach ($facilities as $f)
        <a href="{{ route('calendar', ['facility' => $f->id, 'date' => $date->toDateString(), 'view' => $view]) }}" @class(['fac-tab', 'active' => $f->id === $facility->id])>
            <span class="fac-icon">{!! Icon::svg(Icon::forFacility($f->name_tr), 20) !!}</span>
            <span><strong>{{ $f->name }}</strong><small>{{ __('Kapasite') }} {{ $f->capacity }} · {{ $f->slot_minutes }} {{ __('dk') }}</small></span>
        </a>
    @endforeach
</nav>

<section class="card cal-card">
    <div class="cal-toolbar">
        <div class="cal-nav">
            <a class="icon-btn bordered" href="{{ $nav($date->copy()->subDays($step)) }}" aria-label="{{ __('Önceki') }}">@icon('chevron-left')</a>
            <a class="icon-btn bordered" href="{{ $nav($date->copy()->addDays($step)) }}" aria-label="{{ __('Sonraki') }}">@icon('chevron-right')</a>
            <h2 class="cal-title">
                @if ($view === 'week')
                    {{ $days->first()->locale($loc)->isoFormat('D MMM') }} – {{ $days->last()->locale($loc)->isoFormat('D MMM YYYY') }}
                @else
                    {{ $date->locale($loc)->isoFormat('D MMMM YYYY, dddd') }}
                @endif
            </h2>
        </div>
        <div class="cal-actions">
            <a class="btn btn-sm" href="{{ $nav(today()) }}">{{ __('Bugün') }}</a>
            <form method="get" action="{{ route('calendar') }}" class="inline-form">
                <input type="hidden" name="facility" value="{{ $facility->id }}"><input type="hidden" name="view" value="{{ $view }}">
                <label class="sr-only" for="cal-date">{{ __('Tarih') }}</label>
                <input id="cal-date" class="input-sm" type="date" name="date" value="{{ $date->toDateString() }}" data-autosubmit>
            </form>
            <div class="segmented" role="group" aria-label="{{ __('Görünüm') }}">
                <a href="{{ $nav($date, 'day') }}" @class(['on' => $view === 'day'])>{{ __('Gün') }}</a>
                <a href="{{ $nav($date, 'week') }}" @class(['on' => $view === 'week'])>{{ __('Hafta') }}</a>
            </div>
        </div>
    </div>

    <div class="fac-info">
        @if ($facility->localized('description'))<span>@icon('info', 16) {{ $facility->localized('description') }}</span>@endif
        <span>@icon('users', 16) {{ $facility->group_allowed ? __('Grup rezervasyonuna açık') : __('Yalnızca bireysel') }}</span>
    </div>

    @if ($slotMinutes->isEmpty())
        <div class="empty">@icon('ban', 32)<p>{{ __('Tesis bu tarihlerde kapalı.') }}</p></div>
    @else
    <div class="table-wrap">
        <table @class(['calendar', 'week' => $view === 'week'])>
            <thead>
            <tr>
                <th class="time"><span class="sr-only">{{ __('Saat') }}</span></th>
                @if ($view === 'day')
                    @foreach ($facility->units as $unit)<th>{{ $unit->name }}</th>@endforeach
                @else
                    @foreach ($days as $d)
                        <th @class(['today' => $d->isToday()])><a href="{{ $nav($d, 'day') }}"><small>{{ $d->locale($loc)->isoFormat('ddd') }}</small><b>{{ $d->format('j') }}</b></a></th>
                    @endforeach
                @endif
            </tr>
            </thead>
            <tbody>
            @php $skip = []; @endphp
            @php $lastLabel = []; @endphp
            @foreach ($slotMinutes as $i => $m)
                @php $isNow = $date->isToday() && $view === 'day' && $nowMin >= $m && $nowMin < $m + $facility->slot_minutes; @endphp
                <tr @class(['now' => $isNow])>
                    <td class="time">{{ Availability::minutesToTime($m) }}</td>
                    @if ($view === 'day')
                        @foreach ($facility->units as $unit)
                            @if (($skip[$unit->id] ?? 0) > 0)
                                @php $skip[$unit->id]--; @endphp
                                @continue
                            @endif
                            @php $c = $cell($date, $m, $unit); @endphp
                            @if (isset($c['lock']))
                                @php
                                    $r = $c['lock']->reservation;
                                    $span = 0;
                                    foreach ($slotMinutes->slice($i) as $sm) {
                                        if ($date->copy()->addMinutes($sm)->lt($c['lock']->ends_at)) { $span++; } else { break; }
                                    }
                                    $span = max(1, $span);
                                    $skip[$unit->id] = $span - 1;
                                    $kind = $r->isBlock() ? 'block' : ($c['mine'] ? 'mine' : $r->status);
                                    $lastLabel[$unit->id] = null;
                                @endphp
                                <td rowspan="{{ $span }}" class="cell-event">
                                    <div class="event event-{{ $kind }} @if ($r->status === 'pending') is-pending @endif">
                                        <strong>
                                            @if ($r->isBlock()) {{ $r->title() }}
                                            @elseif ($c['mine']) {{ __('Rezervasyonum') }}
                                            @elseif ($canSeeNames) {{ $r->user?->name }}
                                            @else {{ $r->activity?->name }}
                                            @endif
                                        </strong>
                                        <span class="event-time">{{ $r->starts_at->format('H:i') }}–{{ $r->ends_at->format('H:i') }}</span>
                                        <span class="event-sub">
                                            @if ($r->isBlock()) {{ $r->team?->name ?? __('Sabit blok') }}
                                            @else {{ $r->statusLabel() }}@if ($canSeeNames && ! $c['mine']) · {{ $r->activity?->name }}@endif
                                            @endif
                                        </span>
                                    </div>
                                </td>
                            @elseif ($c['state'] === 'free' && $canBook)
                                @php $lastLabel[$unit->id] = null; @endphp
                                <td><a class="slot-free" href="{{ route('reservations.create', ['facility' => $facility->id, 'date' => $date->toDateString(), 'start' => Availability::minutesToTime($m), 'unit' => $unit->id]) }}" aria-label="{{ $unit->name }} {{ Availability::minutesToTime($m) }} {{ __('boş, rezerve et') }}">@icon('plus', 16)<span>{{ __('Rezerve et') }}</span></a></td>
                            @else
                                @php $label = $c['label'] ?? ''; @endphp
                                <td><div class="slot-{{ $c['state'] === 'free' ? 'idle' : $c['state'] }}">@if ($label && ($lastLabel[$unit->id] ?? null) !== $label)<span>{{ $label }}</span>@endif</div></td>
                                @php $lastLabel[$unit->id] = $label; @endphp
                            @endif
                        @endforeach
                    @else
                        @foreach ($days as $d)
                            @php $c = $cell($d, $m, null); @endphp
                            <td>
                                @if (in_array($c['state'], ['free', 'partial']) && $canBook)
                                    <a class="wk wk-{{ $c['state'] }}" href="{{ route('reservations.create', ['facility' => $facility->id, 'date' => $d->toDateString(), 'start' => Availability::minutesToTime($m)]) }}"><i></i>{{ $c['label'] }}</a>
                                @elseif ($c['state'] === 'busy')
                                    <div class="wk wk-busy">{{ __('Dolu') }}</div>
                                @else
                                    <div class="wk wk-{{ $c['state'] }}">@if ($c['state'] === 'closed' && $m === $slotMinutes->first()){{ $c['label'] }}@endif</div>
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
        @if ($view === 'day')
            <span class="lg lg-free">{{ __('Boş') }}</span><span class="lg lg-pending">{{ __('Beklemede') }}</span>
            <span class="lg lg-approved">{{ __('Onaylı') }}</span><span class="lg lg-mine">{{ __('Benim') }}</span>
            <span class="lg lg-block">{{ __('Sabit blok / antrenman') }}</span><span class="lg lg-closed">{{ __('Kapalı / geçmiş') }}</span>
        @else
            <span class="lg lg-free">{{ __('Tüm alanlar boş') }}</span><span class="lg lg-partial">{{ __('Kısmen dolu') }}</span>
            <span class="lg lg-busy">{{ __('Dolu') }}</span><span class="lg lg-closed">{{ __('Kapalı / geçmiş') }}</span>
        @endif
    </div>
</section>
@endsection
