@extends('layouts.app')
@section('title', __('Giriş (QR)'))
@section('content')
<div class="narrow">
<div class="card empty">
    @if ($reservation)
        <span class="icon-badge green">@icon('check', 24)</span>
        <h1>{{ __('Giriş kaydedildi: :time', ['time' => $reservation->starts_at->format('H:i').'–'.$reservation->ends_at->format('H:i')]) }}</h1>
        <p class="lead">{{ $facility->name }} · {{ $reservation->units->map->name->join(', ') }}</p>
    @else
        <span class="icon-badge amber">@icon('alert', 24)</span>
        <h1>{{ $facility->name }}</h1>
        <p class="lead">{{ __('Şu anda bu tesiste giriş yapılabilecek onaylı bir rezervasyonunuz yok. Giriş, başlangıçtan :e dakika önce ile :g dakika sonrası arasında yapılabilir.', ['e' => \App\Models\Setting::get('checkin_early_minutes'), 'g' => \App\Models\Setting::get('noshow_grace_minutes')]) }}</p>
    @endif
    <a class="btn" href="{{ route('reservations.index') }}">{{ __('Rezervasyonlarım') }}</a>
</div>
</div>
@endsection
