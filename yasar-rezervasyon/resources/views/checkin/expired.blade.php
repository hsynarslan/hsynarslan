@extends('layouts.app')
@section('title', __('Giriş (QR)'))
@section('content')
<div class="narrow">
<div class="card empty">
    <span class="icon-badge amber">@icon('clock', 24)</span>
    <h1>{{ $facility->name }}</h1>
    <p class="lead">{{ __('Bu QR kodunun süresi dolmuş ya da geçersiz. Lütfen tesis girişindeki tablet ekranında gösterilen güncel QR kodu okutun; sorun sürerse salon görevlisine adınızı söyleyin.') }}</p>
    <a class="btn" href="{{ route('reservations.index') }}">{{ __('Rezervasyonlarım') }}</a>
</div>
</div>
@endsection
