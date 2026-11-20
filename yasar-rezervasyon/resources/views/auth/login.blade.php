@extends('layouts.guest')
@section('title', __('Giriş'))
@section('content')
<div class="card">
    <span class="brand-mark">YÜ</span>
    <h1>{{ __('Tesis Rezervasyonu') }}</h1>
    <p class="muted">{{ __('Yaşar Üniversitesi hesabınızla giriş yapın.') }}</p>
    @if ($errors->any())
        <div class="alert alert-danger" role="alert">{{ $errors->first() }}</div>
    @endif
    <form method="post" action="{{ url('/login') }}" autocomplete="on">
        @csrf
        <div class="field">
            <label for="username">{{ __('Kullanıcı adı') }}</label>
            <input id="username" name="username" type="text" value="{{ old('username') }}" required autofocus autocomplete="username" maxlength="100" autocapitalize="none" spellcheck="false">
        </div>
        <div class="field">
            <label for="password">{{ __('Şifre') }}</label>
            <input id="password" name="password" type="password" required autocomplete="current-password" maxlength="200">
        </div>
        <button class="btn btn-primary" type="submit">{{ __('Giriş yap') }}</button>
    </form>
    <form method="post" action="{{ route('locale') }}" class="mt">
        @csrf
        <input type="hidden" name="locale" value="{{ app()->getLocale() === 'tr' ? 'en' : 'tr' }}">
        <button class="btn btn-sm" type="submit">{{ app()->getLocale() === 'tr' ? 'English' : 'Türkçe' }}</button>
    </form>
    @if (config('ldap.driver') === 'fake')
        <div class="alert alert-warning mt small">{{ __('Geliştirme modu: sahte LDAP. Kullanıcılar: ogrenci, akademik, antrenor, gorevli, sksadmin, mezun — şifre: test') }}</div>
    @endif
</div>
@endsection
