@extends('layouts.app')
@section('title', 'QR · '.$facility->name)
@section('content')
<div class="card qr-box">
    <h1>{{ $facility->name }}</h1>
    <p>{{ __('Rezervasyonunuza giriş için QR kodunu telefonunuzun kamerasıyla okutun.') }}</p>
    <p lang="en" class="muted">Scan this QR code with your phone camera to check in to your reservation.</p>
    {!! $svg !!}
    <p class="mono small">{{ $url }}</p>
    <button class="btn btn-primary no-print" type="button" data-print>{{ __('Yazdır') }}</button>
</div>
@endsection
