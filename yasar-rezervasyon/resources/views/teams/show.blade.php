@extends('layouts.app')
@section('title', $team->name)
@section('content')
@php($active = $team->players->whereNull('left_on'))
@php($former = $team->players->whereNotNull('left_on'))
<div class="page-head">
    <div><h1>{{ $team->name }}</h1><p class="muted mt-0">{{ $team->sport }} · {{ __('Antrenör') }}: {{ $team->coach?->name ?? '—' }}</p></div>
    <a class="btn btn-primary" href="{{ route('teams.players.create', $team) }}">+ {{ __('Oyuncu ekle') }}</a>
</div>
<div class="alert alert-info small">{{ __('Oyuncu bilgileri kişisel veridir; sağlık bilgileri özel nitelikli kişisel veridir. Yalnızca takımın antrenörü ve SKS Admin görebilir; her görüntüleme kayda alınır.') }}</div>
<h2>{{ __('Aktif oyuncular') }}</h2>
<div class="table-wrap card">
<table class="table">
    <thead><tr><th>#</th><th>{{ __('Ad Soyad') }}</th><th>{{ __('Öğrenci No') }}</th><th>{{ __('Mevki') }}</th><th>{{ __('Sağlık raporu') }}</th><th></th></tr></thead>
    <tbody>
    @forelse ($active as $p)
        @php($hs = $p->healthStatus($warningDays))
        <tr>
            <td>{{ $p->jersey_no }}</td><td>{{ $p->user->name }}</td><td>{{ $p->user->number }}</td><td>{{ $p->position }}</td>
            <td>
                @if ($hs === 'expired')<span class="badge badge-danger">{{ __('Süresi doldu') }} {{ $p->healthExpiresOn()->format('d.m.Y') }}</span>
                @elseif ($hs === 'soon')<span class="badge badge-soon">{{ __('Yakında doluyor') }} {{ $p->healthExpiresOn()->format('d.m.Y') }}</span>
                @elseif ($hs === 'ok')<span class="badge badge-ok">{{ __('Geçerli') }}</span>
                @else<span class="badge">{{ __('Girilmemiş') }}</span>@endif
            </td>
            <td><a class="btn btn-sm" href="{{ route('teams.players.show', [$team, $p]) }}">{{ __('Detay') }}</a></td>
        </tr>
    @empty
        <tr><td colspan="6" class="muted">{{ __('Oyuncu yok.') }}</td></tr>
    @endforelse
    </tbody>
</table>
</div>
@if ($former->isNotEmpty())
<h2>{{ __('Eski oyuncular') }}</h2>
<div class="table-wrap card">
<table class="table">
    <thead><tr><th>#</th><th>{{ __('Ad Soyad') }}</th><th>{{ __('Öğrenci No') }}</th><th>{{ __('Mevki') }}</th><th>{{ __('Takımda') }}</th></tr></thead>
    <tbody>
    @foreach ($former as $p)
        <tr><td>{{ $p->jersey_no }}</td><td>{{ $p->user->name }}</td><td>{{ $p->user->number }}</td><td>{{ $p->position }}</td>
            <td class="nowrap">{{ $p->joined_on?->format('d.m.Y') }} – {{ $p->left_on->format('d.m.Y') }}</td></tr>
    @endforeach
    </tbody>
</table>
</div>
@endif
@endsection
