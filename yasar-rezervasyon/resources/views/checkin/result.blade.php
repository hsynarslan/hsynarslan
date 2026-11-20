@extends('layouts.app')
@section('title', __('Giriş (QR)'))
@section('content')
<div class="card">
    <h1>{{ $facility->name }}</h1>
    @if ($reservation)
        <div class="alert alert-success">{{ __('Giriş kaydedildi: :time', ['time' => $reservation->starts_at->format('H:i').'–'.$reservation->ends_at->format('H:i')]) }}</div>
    @else
        <div class="alert alert-warning">{{ __('Şu anda bu tesiste giriş yapılabilecek onaylı bir rezervasyonunuz yok. Giriş, başlangıçtan :e dakika önce ile :g dakika sonrası arasında yapılabilir.', ['e' => \App\Models\Setting::get('checkin_early_minutes'), 'g' => \App\Models\Setting::get('noshow_grace_minutes')]) }}</div>
    @endif
    <a class="btn" href="{{ route('reservations.index') }}">{{ __('Rezervasyonlarım') }}</a>
</div>
@endsection
