@extends('layouts.app')
@section('title', __('Onay Bekleyenler'))
@section('content')
@php($loc = app()->getLocale())
<div class="page-head">
    <div><h1>{{ __('Onay Bekleyenler') }}</h1>
        <p class="lead">{{ __('Başlangıç saatine kadar onaylanmayan talepler otomatik iptal edilir.') }}</p></div>
</div>

<div class="stats">
    <div class="stat"><span class="icon-badge amber">@icon('clock')</span><div><b>{{ $pending->count() }}</b><span>{{ __('Onay bekleyen') }}</span></div></div>
    <div class="stat"><span class="icon-badge red">@icon('alert')</span><div><b>{{ $pending->filter(fn ($r) => $r->starts_at->lt(now()->addHours(4)))->count() }}</b><span>{{ __('4 saat içinde başlayacak') }}</span></div></div>
    <div class="stat"><span class="icon-badge green">@icon('check-circle')</span><div><b>{{ $upcoming->count() }}</b><span>{{ __('Onaylı (14 gün)') }}</span></div></div>
</div>

<div class="res-list">
@forelse ($pending as $r)
    @php($urgent = $r->starts_at->lt(now()->addHours(4)))
    <article @class(['res', 's-pending', 'urgent' => $urgent])>
        <div class="res-date">
            <small>{{ $r->starts_at->locale($loc)->isoFormat('MMM') }}</small><b>{{ $r->starts_at->format('j') }}</b><span>{{ $r->starts_at->locale($loc)->isoFormat('ddd') }}</span>
        </div>
        <div class="res-main">
            <div class="res-title">
                <strong>{{ $r->user?->name }}</strong>
                <span class="muted small">{{ $r->user?->number }} · {{ $r->user ? implode(', ', $r->user->roleLabels()) : '' }}</span>
                @if ($urgent)<span class="badge badge-pending">{{ $r->starts_at->locale($loc)->diffForHumans() }}</span>@endif
            </div>
            <div class="res-meta">
                <span>@icon('map-pin', 15) {{ $r->facility->name }} · {{ $r->units->map->name->join(', ') }}</span>
                <span>@icon('clock', 15) {{ $r->starts_at->format('H:i') }}–{{ $r->ends_at->format('H:i') }}</span>
                <span>@icon('ball', 15) {{ $r->title() }}</span>
                <span>@icon('users', 15) {{ trans_choice(':count kişi', $r->participant_count) }}</span>
                @if ($r->team)<span>@icon('trophy', 15) {{ $r->team->name }}</span>@endif
            </div>
            <div class="res-extra">
                @if ($r->participantUsers->isNotEmpty())<div>@icon('users', 15) {{ $r->participantUsers->map(fn ($p) => $p->name.' ('.$p->number.')')->join(', ') }}</div>@endif
                @if ($r->equipmentItems->isNotEmpty())<div>@icon('package', 15) {{ $r->equipmentItems->map(fn ($e) => $e->quantity.' × '.$e->equipment->name)->join(', ') }}</div>@endif
                @if ($r->notes)<div>@icon('file-text', 15) {{ $r->notes }}</div>@endif
            </div>
            <div class="res-code">{{ $r->code }} · {{ __('Talep') }}: {{ $r->created_at->format('d.m.Y H:i') }}</div>
        </div>
        <div class="res-side">
            <form method="post" action="{{ route('approvals.approve', $r) }}">@csrf<button class="btn btn-success" type="submit">@icon('check', 16) {{ __('Onayla') }}</button></form>
            <details class="inline-details">
                <summary class="btn btn-danger">@icon('x', 16) {{ __('Reddet') }}</summary>
                <form class="details-body" method="post" action="{{ route('approvals.reject', $r) }}">@csrf
                    <label for="reason-{{ $r->id }}">{{ __('Red gerekçesi (kullanıcıya gönderilir)') }}</label>
                    <textarea id="reason-{{ $r->id }}" name="reason" required minlength="3" maxlength="500"></textarea>
                    <button class="btn btn-danger btn-sm mt" type="submit">{{ __('Reddet') }}</button>
                </form>
            </details>
        </div>
    </article>
@empty
    <div class="card empty">@icon('check-circle', 40)<p>{{ __('Onay bekleyen talep yok.') }}</p></div>
@endforelse
</div>

<h2>{{ __('Onaylı yaklaşan rezervasyonlar (14 gün)') }}</h2>
<div class="card table-wrap">
<table class="table">
    <thead><tr><th>{{ __('Tarih') }}</th><th>{{ __('Tesis') }}</th><th>{{ __('Alan') }}</th><th>{{ __('Kişi') }}</th><th></th></tr></thead>
    <tbody>
    @forelse ($upcoming as $r)
        <tr>
            <td class="nowrap"><strong>{{ $r->starts_at->locale($loc)->isoFormat('D MMM ddd') }}</strong> <span class="muted">{{ $r->starts_at->format('H:i') }}–{{ $r->ends_at->format('H:i') }}</span></td>
            <td>{{ $r->facility->name }} <span class="muted">· {{ $r->title() }}</span></td>
            <td>{{ $r->units->map->name->join(', ') }}</td>
            <td>{{ $r->user?->name }}</td>
            <td class="right">
                <details class="inline-details"><summary class="btn btn-sm btn-danger">{{ __('İptal et') }}</summary>
                    <form class="details-body" method="post" action="{{ route('approvals.cancel', $r) }}">@csrf
                        <label for="c-{{ $r->id }}">{{ __('İptal gerekçesi') }}</label>
                        <textarea id="c-{{ $r->id }}" name="reason" required minlength="3" maxlength="500"></textarea>
                        <button class="btn btn-danger btn-sm mt" type="submit">{{ __('İptal et') }}</button>
                    </form></details>
            </td>
        </tr>
    @empty
        <tr><td colspan="5" class="muted">{{ __('Kayıt yok.') }}</td></tr>
    @endforelse
    </tbody>
</table>
</div>
@endsection
