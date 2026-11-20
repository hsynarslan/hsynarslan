@extends('layouts.app')
@section('title', __('Onay Bekleyenler'))
@section('content')
<div class="page-head"><div><h1>{{ __('Onay Bekleyenler') }}</h1>
    <p class="muted mt-0">{{ __('Başlangıç saatine kadar onaylanmayan talepler otomatik iptal edilir.') }}</p></div></div>

@forelse ($pending as $r)
    @php($urgent = $r->starts_at->lt(now()->addHours(4)))
    <article @class(['card', 'alert-warning' => $urgent])>
        <div class="card-head">
            <div>
                <div class="actions">
                    <strong>{{ $r->facility->name }} · {{ $r->title() }}</strong>
                    <span class="badge">{{ $r->kindLabel() }}</span>
                    @if ($urgent)<span class="badge badge-pending">{{ __('Yaklaşıyor') }}: {{ $r->starts_at->locale(app()->getLocale())->diffForHumans() }}</span>@endif
                </div>
                <div class="res-meta">
                    <span>{{ $r->starts_at->locale(app()->getLocale())->isoFormat('ddd, D MMM YYYY') }} {{ $r->starts_at->format('H:i') }}–{{ $r->ends_at->format('H:i') }}</span>
                    <span>{{ $r->units->map->name->join(', ') }}</span>
                    <span>{{ trans_choice(':count kişi', $r->participant_count) }}</span>
                </div>
                <div class="small">{{ $r->user?->name }} ({{ $r->user?->number }}) · {{ $r->user ? implode(', ', $r->user->roleLabels()) : '' }}</div>
                @if ($r->team)<div class="small">{{ __('Takım') }}: {{ $r->team->name }}</div>@endif
                @if ($r->participantUsers->isNotEmpty())<div class="small muted">{{ __('Katılımcılar') }}: {{ $r->participantUsers->map(fn ($p) => $p->name.' ('.$p->number.')')->join(', ') }}</div>@endif
                @if ($r->equipmentItems->isNotEmpty())<div class="small muted">{{ __('Ekipman') }}: {{ $r->equipmentItems->map(fn ($e) => $e->quantity.' × '.$e->equipment->name)->join(', ') }}</div>@endif
                @if ($r->notes)<div class="small muted">{{ __('Not') }}: {{ $r->notes }}</div>@endif
                <div class="res-code">{{ $r->code }} · {{ __('Talep') }}: {{ $r->created_at->format('d.m.Y H:i') }}</div>
            </div>
            <div class="actions">
                <form method="post" action="{{ route('approvals.approve', $r) }}">@csrf<button class="btn btn-success" type="submit">{{ __('Onayla') }}</button></form>
                <details class="inline-details">
                    <summary class="btn btn-danger">{{ __('Reddet') }}</summary>
                    <form class="details-body" method="post" action="{{ route('approvals.reject', $r) }}">@csrf
                        <label for="reason-{{ $r->id }}">{{ __('Red gerekçesi (kullanıcıya gönderilir)') }}</label>
                        <textarea id="reason-{{ $r->id }}" name="reason" required minlength="3" maxlength="500"></textarea>
                        <button class="btn btn-danger mt" type="submit">{{ __('Reddet') }}</button>
                    </form>
                </details>
            </div>
        </div>
    </article>
@empty
    <div class="card muted">{{ __('Onay bekleyen talep yok.') }}</div>
@endforelse

<h2>{{ __('Onaylı yaklaşan rezervasyonlar (14 gün)') }}</h2>
<div class="table-wrap card">
<table class="table">
    <thead><tr><th>{{ __('Tarih') }}</th><th>{{ __('Tesis') }}</th><th>{{ __('Alan') }}</th><th>{{ __('Kişi') }}</th><th></th></tr></thead>
    <tbody>
    @forelse ($upcoming as $r)
        <tr>
            <td class="nowrap">{{ $r->starts_at->format('d.m.Y H:i') }}–{{ $r->ends_at->format('H:i') }}</td>
            <td>{{ $r->facility->name }} · {{ $r->title() }}</td>
            <td>{{ $r->units->map->name->join(', ') }}</td>
            <td>{{ $r->user?->name }}</td>
            <td>
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
