@extends('layouts.guest')
@section('title', __('Giriş'))
@section('content')
<div class="login">
    <section class="login-hero" aria-hidden="true">
        <div class="hero-inner">
            <div class="brand brand-light"><span class="brand-mark">YÜ</span>
                <span class="brand-text"><strong>{{ __('Yaşar Üniversitesi') }}</strong><small>{{ __('Sağlık, Kültür ve Spor Daire Başkanlığı') }}</small></span></div>
            <h1>{{ __('Kampüs tesislerini birkaç tıkla rezerve edin.') }}</h1>
            <p>{{ __('Spor salonu, dans salonu ve çalışma odalarının doluluğunu canlı görün, talebinizi gönderin, onay e-postanızı alın.') }}</p>
            <ul class="hero-list">
                <li>@icon('calendar', 20) {{ __('Gün ve hafta takvimi') }}</li>
                <li>@icon('check-circle', 20) {{ __('SKS onayı ve e-posta bildirimleri') }}</li>
                <li>@icon('qr', 20) {{ __('QR kod ile hızlı giriş') }}</li>
            </ul>
            <div class="hero-court">
                <div class="court-half"><span>{{ __('Pota A') }}</span></div><div class="court-line"></div><div class="court-half"><span>{{ __('Pota B') }}</span></div>
            </div>
        </div>
    </section>

    <section class="login-panel">
        <div class="login-card">
            <div class="brand brand-compact login-mobile-brand"><span class="brand-mark">YÜ</span><strong>{{ __('Tesis Rezervasyonu') }}</strong></div>
            <h2>{{ __('Hoş geldiniz') }}</h2>
            <p class="muted">{{ __('Yaşar Üniversitesi hesabınızla giriş yapın.') }}</p>
            @if ($errors->any())
                <div class="alert alert-danger" role="alert">@icon('alert', 20)<div>{{ $errors->first() }}</div></div>
            @endif
            <form method="post" action="{{ url('/login') }}" autocomplete="on">
                @csrf
                <div class="field">
                    <label for="username">{{ __('Kullanıcı adı') }}</label>
                    <div class="input-icon">@icon('user')
                        <input id="username" name="username" type="text" value="{{ old('username') }}" required autofocus autocomplete="username" maxlength="100" autocapitalize="none" spellcheck="false" placeholder="ad.soyad">
                    </div>
                </div>
                <div class="field">
                    <label for="password">{{ __('Şifre') }}</label>
                    <div class="input-icon">@icon('lock')
                        <input id="password" name="password" type="password" required autocomplete="current-password" maxlength="200" placeholder="••••••••">
                    </div>
                </div>
                <button class="btn btn-primary btn-block btn-lg" type="submit">{{ __('Giriş yap') }}</button>
            </form>
            <div class="login-foot">
                <form method="post" action="{{ route('locale') }}">
                    @csrf
                    <input type="hidden" name="locale" value="{{ app()->getLocale() === 'tr' ? 'en' : 'tr' }}">
                    <button class="link" type="submit">@icon('globe', 16) {{ app()->getLocale() === 'tr' ? 'English' : 'Türkçe' }}</button>
                </form>
                <span class="muted small">@icon('lock', 14) {{ __('Bağlantı şifreli · KVKK') }}</span>
            </div>
            @if (config('ldap.driver') === 'fake')
                <div class="alert alert-warning small mt">@icon('info', 18)<div>{{ __('Geliştirme modu: sahte LDAP. Kullanıcılar: ogrenci, akademik, antrenor, gorevli, sksadmin, mezun — şifre: test') }}</div></div>
            @endif
        </div>
    </section>
</div>
@endsection
