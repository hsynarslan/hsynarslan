@extends('layouts.app')
@section('title', __('Takımlar'))
@section('content')
<div class="page-head"><h1>{{ auth()->user()->is_sks_admin ? __('Takımlar') : __('Takımlarım') }}</h1>
    @if (auth()->user()->is_sks_admin)<a class="btn" href="{{ route('admin.teams.index') }}">{{ __('Takım yönetimi') }}</a>@endif</div>
<div class="grid grid-3">
@forelse ($teams as $t)
    <a class="card" href="{{ route('teams.show', $t) }}">
        <strong>{{ $t->name }}</strong> @unless ($t->is_active)<span class="badge">{{ __('Pasif') }}</span>@endunless
        <div class="muted small">{{ $t->sport }} · {{ __('Antrenör') }}: {{ $t->coach?->name ?? '—' }}</div>
        <div class="small">{{ trans_choice(':count oyuncu', $t->active_players_count) }}</div>
    </a>
@empty
    <div class="card muted">{{ __('Size atanmış bir takım yok.') }}</div>
@endforelse
</div>
@endsection
