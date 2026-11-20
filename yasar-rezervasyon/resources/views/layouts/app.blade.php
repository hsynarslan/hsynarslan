<!doctype html>
<html lang="{{ app()->getLocale() }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="theme-color" content="#1d3b6e">
    <title>@yield('title', __('Tesis Rezervasyonu')) · {{ __('Yaşar Üniversitesi') }}</title>
    <link rel="stylesheet" href="{{ asset('css/app.css') }}">
    <link rel="manifest" href="{{ asset('manifest.webmanifest') }}">
    <link rel="icon" href="{{ asset('icons/icon.svg') }}" type="image/svg+xml">
    <script src="{{ asset('js/app.js') }}" defer></script>
</head>
<body>
@php($u = auth()->user())
<header class="topbar">
    <div class="topbar-inner">
        <a class="brand" href="{{ route('calendar') }}"><span class="brand-mark">YÜ</span><span>{{ __('Tesis Rezervasyonu') }}</span></a>
        @if ($u)
            <nav class="nav" aria-label="{{ __('Ana menü') }}">
                <a href="{{ route('calendar') }}" @class(['active' => request()->routeIs('calendar')])>{{ __('Takvim') }}</a>
                <a href="{{ route('reservations.index') }}" @class(['active' => request()->routeIs('reservations.*')])>{{ __('Rezervasyonlarım') }}</a>
                @if ($u->is_coach && ! $u->is_sks_admin)
                    <a href="{{ route('teams.index') }}" @class(['active' => request()->routeIs('teams.*')])>{{ __('Takımlarım') }}</a>
                @endif
                @if ($u->is_attendant || $u->is_sks_admin)
                    <a href="{{ route('attendant.index') }}" @class(['active' => request()->routeIs('attendant.*')])>{{ __('Günlük Liste') }}</a>
                @endif
                @if ($u->isApprover())
                    @php($ids = $u->approvableFacilityIds())
                    @php($pendingCount = \App\Models\Reservation::where('status', 'pending')->where('starts_at', '>', now())->when($ids !== null, fn ($q) => $q->whereIn('facility_id', $ids))->count())
                    <a href="{{ route('approvals.index') }}" @class(['active' => request()->routeIs('approvals.*')])>{{ __('Onay Bekleyenler') }}@if ($pendingCount)<span class="count">{{ $pendingCount }}</span>@endif</a>
                    <a href="{{ route('reports.index') }}" @class(['active' => request()->routeIs('reports.*')])>{{ __('Raporlar') }}</a>
                @endif
                @if ($u->is_sks_admin)
                    <a href="{{ route('admin.facilities.index') }}" @class(['active' => request()->routeIs('admin.*')])>{{ __('Yönetim') }}</a>
                @endif
            </nav>
        @endif
        <div class="userbox">
            <form method="post" action="{{ route('locale') }}">
                @csrf
                <input type="hidden" name="locale" value="{{ app()->getLocale() === 'tr' ? 'en' : 'tr' }}">
                <button class="link-btn" type="submit" lang="{{ app()->getLocale() === 'tr' ? 'en' : 'tr' }}">{{ app()->getLocale() === 'tr' ? 'EN' : 'TR' }}</button>
            </form>
            @if ($u)
                <span title="{{ implode(', ', $u->roleLabels()) }}">{{ $u->name }}</span>
                <form method="post" action="{{ route('logout') }}">@csrf<button class="link-btn" type="submit">{{ __('Çıkış') }}</button></form>
            @endif
        </div>
    </div>
</header>
@if ($u && $u->is_sks_admin && request()->routeIs('admin.*'))
    <div class="subnav">
        <nav class="nav" aria-label="{{ __('Yönetim menüsü') }}">
            @foreach ([
                'admin.facilities.index' => 'Tesisler', 'admin.blocks.index' => 'Sabit Bloklar', 'admin.closures.index' => 'Kapatmalar',
                'admin.holidays.index' => 'Resmi Tatiller', 'admin.teams.index' => 'Takımlar', 'admin.equipment.index' => 'Ekipman',
                'admin.users.index' => 'Kullanıcılar', 'admin.settings.edit' => 'Ayarlar', 'admin.kvkk.index' => 'KVKK Metni',
                'admin.audit.index' => 'İşlem Kaydı',
            ] as $route => $label)
                <a href="{{ route($route) }}" @class(['active' => request()->routeIs(\Illuminate\Support\Str::beforeLast($route, '.').'.*')])>{{ __($label) }}</a>
            @endforeach
        </nav>
    </div>
@endif
<main id="main">
    @foreach (['success', 'warning', 'danger', 'info'] as $type)
        @if (session($type))<div class="alert alert-{{ $type }}" role="status">{{ session($type) }}</div>@endif
    @endforeach
    @if ($errors->any())
        <div class="alert alert-danger" role="alert">
            <strong>{{ __('İşlem tamamlanamadı:') }}</strong>
            <ul>@foreach ($errors->all() as $e)<li>{{ $e }}</li>@endforeach</ul>
        </div>
    @endif
    @yield('content')
</main>
</body>
</html>
