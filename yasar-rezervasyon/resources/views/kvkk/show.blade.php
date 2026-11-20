@extends('layouts.app')
@section('title', __('KVKK Aydınlatma Metni'))
@section('content')
<div class="card">
    <h1>{{ __('KVKK Aydınlatma Metni') }}</h1>
    <p class="muted small">{{ __('Sürüm') }} {{ $text->version }} · {{ $text->published_at->format('d.m.Y') }}</p>
    <div class="scroll-box" tabindex="0">{{ $text->body() }}</div>
    <form method="post" action="{{ route('kvkk.accept') }}">
        @csrf
        <label class="check"><input type="checkbox" name="accept" value="1" required> {{ __('Aydınlatma metnini okudum ve anladım, onaylıyorum.') }}</label>
        <button class="btn btn-primary mt" type="submit">{{ __('Onayla ve devam et') }}</button>
    </form>
</div>
@endsection
