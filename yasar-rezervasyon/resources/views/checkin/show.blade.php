@extends('layouts.app')
@section('title', __('Giriş (QR)'))
@section('content')
<div class="card">
    <h1>{{ $facility->name }}</h1>
    <p>{{ __('Rezervasyonunuza giriş yapmak için onaylayın.') }}</p>
    <form method="post" action="{{ route('checkin.store', $token) }}">@csrf
        <button class="btn btn-primary" type="submit">{{ __('Girişimi kaydet') }}</button></form>
</div>
@endsection
