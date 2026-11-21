<!doctype html>
<html lang="{{ app()->getLocale() }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>{{ $facility->name }} · QR</title>
    <link rel="stylesheet" href="{{ asset('css/app.css') }}?v=3">
    <script src="{{ asset('js/app.js') }}?v=3" defer></script>
</head>
<body class="kiosk">
<main class="kiosk-main">
    <div class="brand brand-light"><span class="brand-mark">YÜ</span><span class="brand-text"><strong>{{ __('Yaşar Üniversitesi') }}</strong><small>{{ __('Tesis Rezervasyonu') }}</small></span></div>
    <h1>{{ $facility->name }}</h1>
    <p class="kiosk-lead">{{ __('Rezervasyonunuza giriş için QR kodunu telefonunuzun kamerasıyla okutun.') }}<br><span lang="en">Scan the QR code with your phone camera to check in.</span></p>
    <div class="kiosk-qr"><img src="{{ route('kiosk.qr', $facility) }}" alt="{{ __('Giriş QR kodu') }}" data-refresh="{{ $period * 1000 }}" width="420" height="420"></div>
    <p class="kiosk-note">@icon('clock', 16) {{ __('Kod her :s saniyede bir yenilenir. Ekran görüntüsü veya fotoğraf sonradan çalışmaz.', ['s' => $period]) }}</p>
    <p class="kiosk-clock" data-clock></p>
</main>
</body>
</html>
