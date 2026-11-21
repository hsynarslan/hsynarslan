@extends('layouts.app')
@section('title', __('Giriş (QR)'))
@section('content')
<div class="narrow">
<div class="card empty">
    <span class="icon-badge">@icon('qr', 24)</span>
    <h1>{{ $facility->name }}</h1>
    <p class="lead">{{ __('Rezervasyonunuza giriş yapmak için onaylayın.') }}</p>
    <form method="post" action="{{ $action }}">@csrf
        <button class="btn btn-primary btn-lg" type="submit">@icon('check') {{ __('Girişimi kaydet') }}</button></form>
</div>
</div>
@endsection
