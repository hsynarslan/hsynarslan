@extends('layouts.app')
@section('title', __('Rezervasyonlarım'))
@section('content')
<div class="page-head"><h1>{{ __('Rezervasyonlarım') }}</h1>
    <a class="btn btn-primary" href="{{ route('reservations.create') }}">+ {{ __('Yeni rezervasyon') }}</a></div>
@if ($sanction)
    <div class="alert alert-danger">{{ __(':date tarihine kadar rezervasyon yapamazsınız.', ['date' => $sanction->ends_at->format('d.m.Y H:i')]) }} ({{ $sanction->reason }})</div>
@endif
<nav class="tabs">
    <a href="{{ route('reservations.index') }}" @class(['active' => $tab === 'upcoming'])>{{ __('Yaklaşan') }}</a>
    <a href="{{ route('reservations.index', ['tab' => 'past']) }}" @class(['active' => $tab === 'past'])>{{ __('Geçmiş') }}</a>
    <a href="{{ route('reservations.index', ['tab' => 'participating']) }}" @class(['active' => $tab === 'participating'])>{{ __('Katılımcı olduklarım') }}</a>
</nav>
@forelse ($reservations as $r)
    @include('reservations._card', $tab === 'participating' ? ['r' => $r, 'participating' => true] : ['r' => $r])
@empty
    <div class="card muted">{{ __('Kayıt yok.') }}</div>
@endforelse
{{ $reservations->links() }}
@endsection
