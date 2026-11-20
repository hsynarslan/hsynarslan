@extends('layouts.app')
@section('title', __('Rezervasyonlarım'))
@section('content')
<div class="page-head">
    <div><h1>{{ __('Rezervasyonlarım') }}</h1><p class="lead">{{ __('Taleplerinizin durumunu takip edin, gerekirse iptal edin.') }}</p></div>
    <a class="btn btn-primary" href="{{ route('reservations.create') }}">@icon('plus') {{ __('Yeni rezervasyon') }}</a>
</div>
@if ($sanction)
    <div class="alert alert-danger">@icon('ban', 20)<div>{{ __(':date tarihine kadar rezervasyon yapamazsınız.', ['date' => $sanction->ends_at->format('d.m.Y H:i')]) }} ({{ $sanction->reason }})</div></div>
@endif
<nav class="tabs">
    <a href="{{ route('reservations.index') }}" @class(['active' => $tab === 'upcoming'])>{{ __('Yaklaşan') }}</a>
    <a href="{{ route('reservations.index', ['tab' => 'past']) }}" @class(['active' => $tab === 'past'])>{{ __('Geçmiş') }}</a>
    <a href="{{ route('reservations.index', ['tab' => 'participating']) }}" @class(['active' => $tab === 'participating'])>{{ __('Katılımcı olduklarım') }}</a>
</nav>
<div class="res-list">
@forelse ($reservations as $r)
    @include('reservations._card', $tab === 'participating' ? ['r' => $r, 'participating' => true] : ['r' => $r])
@empty
    <div class="card empty">@icon('calendar', 36)<p>{{ __('Burada henüz bir rezervasyon yok.') }}</p>
        <a class="btn btn-primary" href="{{ route('calendar') }}">{{ __('Takvime git') }}</a></div>
@endforelse
</div>
{{ $reservations->links() }}
@endsection
