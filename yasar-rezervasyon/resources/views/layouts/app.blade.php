<!doctype html>
<html lang="{{ app()->getLocale() }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="theme-color" content="#0f2557">
    <title>@yield('title', __('Tesis Rezervasyonu')) · {{ __('Yaşar Üniversitesi') }}</title>
    <link rel="preload" href="{{ asset('fonts/inter-latin-wght-normal.woff2') }}" as="font" type="font/woff2" crossorigin>
    <link rel="stylesheet" href="{{ asset('css/app.css') }}?v=2">
    <link rel="manifest" href="{{ asset('manifest.webmanifest') }}">
    <link rel="icon" href="{{ asset('icons/icon.svg') }}" type="image/svg+xml">
    <script src="{{ asset('js/app.js') }}?v=2" defer></script>
</head>
<body class="app">
@php
    $u = auth()->user();
    $is = fn (string ...$patterns) => request()->routeIs(...$patterns);
    $pendingCount = 0;
    if ($u && $u->isApprover()) {
        $ids = $u->approvableFacilityIds();
        $pendingCount = \App\Models\Reservation::where('status', 'pending')->where('starts_at', '>', now())
            ->when($ids !== null, fn ($q) => $q->whereIn('facility_id', $ids))->count();
    }
    $initials = $u ? collect(explode(' ', $u->name))->filter()->map(fn ($p) => mb_substr($p, 0, 1))->take(2)->join('') : '';
@endphp

<aside class="sidebar" id="sidebar" aria-label="{{ __('Ana menü') }}">
    <div class="sidebar-head">
        <a class="brand" href="{{ route('calendar') }}">
            <span class="brand-mark">YÜ</span>
            <span class="brand-text"><strong>{{ __('Yaşar Üniversitesi') }}</strong><small>{{ __('Tesis Rezervasyonu') }}</small></span>
        </a>
        <button class="icon-btn sidebar-close" type="button" data-sidebar-close aria-label="{{ __('Menüyü kapat') }}">@icon('x')</button>
    </div>

    <nav class="side-nav">
        <a href="{{ route('calendar') }}" @class(['active' => $is('calendar')])>@icon('calendar') <span>{{ __('Takvim') }}</span></a>
        <a href="{{ route('reservations.create') }}" @class(['active' => $is('reservations.create')])>@icon('calendar-plus') <span>{{ __('Yeni rezervasyon') }}</span></a>
        <a href="{{ route('reservations.index') }}" @class(['active' => $is('reservations.index')])>@icon('list') <span>{{ __('Rezervasyonlarım') }}</span></a>
        @if ($u->is_coach && ! $u->is_sks_admin)
            <a href="{{ route('teams.index') }}" @class(['active' => $is('teams.*')])>@icon('trophy') <span>{{ __('Takımlarım') }}</span></a>
        @endif

        @if ($u->isApprover() || $u->is_attendant)
            <div class="side-label">{{ __('SKS') }}</div>
            @if ($u->isApprover())
                <a href="{{ route('approvals.index') }}" @class(['active' => $is('approvals.*')])>@icon('check-circle') <span>{{ __('Onay Bekleyenler') }}</span>@if ($pendingCount)<b class="pill">{{ $pendingCount }}</b>@endif</a>
            @endif
            @if ($u->is_attendant || $u->is_sks_admin)
                <a href="{{ route('attendant.index') }}" @class(['active' => $is('attendant.*')])>@icon('clipboard') <span>{{ __('Günlük Liste') }}</span></a>
            @endif
            @if ($u->isApprover())
                <a href="{{ route('reports.index') }}" @class(['active' => $is('reports.*')])>@icon('chart') <span>{{ __('Raporlar') }}</span></a>
            @endif
        @endif

        @if ($u->is_sks_admin)
            <div class="side-label">{{ __('Yönetim') }}</div>
            <a href="{{ route('admin.facilities.index') }}" @class(['active' => $is('admin.facilities.*')])>@icon('building') <span>{{ __('Tesisler') }}</span></a>
            <a href="{{ route('admin.blocks.index') }}" @class(['active' => $is('admin.blocks.*')])>@icon('repeat') <span>{{ __('Sabit Bloklar') }}</span></a>
            <a href="{{ route('admin.closures.index') }}" @class(['active' => $is('admin.closures.*')])>@icon('ban') <span>{{ __('Kapatmalar') }}</span></a>
            <a href="{{ route('admin.holidays.index') }}" @class(['active' => $is('admin.holidays.*')])>@icon('sun') <span>{{ __('Resmi Tatiller') }}</span></a>
            <a href="{{ route('admin.teams.index') }}" @class(['active' => $is('admin.teams.*', 'teams.*')])>@icon('trophy') <span>{{ __('Takımlar') }}</span></a>
            <a href="{{ route('admin.equipment.index') }}" @class(['active' => $is('admin.equipment.*')])>@icon('package') <span>{{ __('Ekipman') }}</span></a>
            <a href="{{ route('admin.users.index') }}" @class(['active' => $is('admin.users.*')])>@icon('users') <span>{{ __('Kullanıcılar') }}</span></a>
            <a href="{{ route('admin.settings.edit') }}" @class(['active' => $is('admin.settings.*')])>@icon('settings') <span>{{ __('Ayarlar') }}</span></a>
            <a href="{{ route('admin.kvkk.index') }}" @class(['active' => $is('admin.kvkk.*')])>@icon('file-text') <span>{{ __('KVKK Metni') }}</span></a>
            <a href="{{ route('admin.audit.index') }}" @class(['active' => $is('admin.audit.*')])>@icon('shield') <span>{{ __('İşlem Kaydı') }}</span></a>
        @endif
    </nav>

    <div class="sidebar-foot">
        <div class="me">
            <span class="avatar">{{ $initials }}</span>
            <span class="me-text"><strong>{{ $u->name }}</strong><small>{{ implode(' · ', $u->roleLabels()) }}</small></span>
        </div>
        <div class="me-actions">
            <form method="post" action="{{ route('locale') }}">
                @csrf
                <input type="hidden" name="locale" value="{{ app()->getLocale() === 'tr' ? 'en' : 'tr' }}">
                <button class="ghost-btn" type="submit">@icon('globe', 16) {{ app()->getLocale() === 'tr' ? 'English' : 'Türkçe' }}</button>
            </form>
            <form method="post" action="{{ route('logout') }}">@csrf<button class="ghost-btn" type="submit">@icon('log-out', 16) {{ __('Çıkış') }}</button></form>
        </div>
    </div>
</aside>
<div class="scrim" data-sidebar-close></div>

<div class="shell">
    <header class="topbar">
        <button class="icon-btn" type="button" data-sidebar-open aria-label="{{ __('Menüyü aç') }}" aria-controls="sidebar">@icon('menu', 22)</button>
        <a class="brand brand-compact" href="{{ route('calendar') }}"><span class="brand-mark">YÜ</span><strong>{{ __('Tesis Rezervasyonu') }}</strong></a>
        <a class="avatar avatar-sm" href="{{ route('reservations.index') }}" aria-label="{{ __('Rezervasyonlarım') }}">{{ $initials }}</a>
    </header>

    <main id="main" class="content">
        @foreach (['success' => 'check-circle', 'warning' => 'alert', 'danger' => 'alert', 'info' => 'info'] as $type => $icon)
            @if (session($type))<div class="alert alert-{{ $type }}" role="status">@icon($icon, 20)<div>{{ session($type) }}</div></div>@endif
        @endforeach
        @if ($errors->any())
            <div class="alert alert-danger" role="alert">@icon('alert', 20)
                <div><strong>{{ __('İşlem tamamlanamadı:') }}</strong>
                    <ul>@foreach ($errors->all() as $e)<li>{{ $e }}</li>@endforeach</ul></div>
            </div>
        @endif
        @yield('content')
    </main>
</div>
</body>
</html>
