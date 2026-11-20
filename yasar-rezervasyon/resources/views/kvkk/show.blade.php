@extends('layouts.app')
@section('title', __('KVKK Aydınlatma Metni'))
@section('content')
<div class="narrow">
    <div class="page-head"><div><span class="eyebrow">{{ __('Sürüm') }} {{ $text->version }} · {{ $text->published_at->format('d.m.Y') }}</span>
        <h1>{{ __('KVKK Aydınlatma Metni') }}</h1><p class="lead">{{ __('Devam etmeden önce lütfen aydınlatma metnini okuyun.') }}</p></div></div>
    <div class="card">
        <div class="scroll-box" tabindex="0">{{ $text->body() }}</div>
        <form method="post" action="{{ route('kvkk.accept') }}">
            @csrf
            <label class="check check-lg"><input type="checkbox" name="accept" value="1" required> {{ __('Aydınlatma metnini okudum ve anladım, onaylıyorum.') }}</label>
            <button class="btn btn-primary mt" type="submit">@icon('check') {{ __('Onayla ve devam et') }}</button>
        </form>
    </div>
</div>
@endsection
