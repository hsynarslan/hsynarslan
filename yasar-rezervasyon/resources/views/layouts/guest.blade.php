<!doctype html>
<html lang="{{ app()->getLocale() }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="theme-color" content="#1d3b6e">
    <title>@yield('title') · {{ __('Yaşar Üniversitesi') }}</title>
    <link rel="stylesheet" href="{{ asset('css/app.css') }}">
    <link rel="manifest" href="{{ asset('manifest.webmanifest') }}">
    <link rel="icon" href="{{ asset('icons/icon.svg') }}" type="image/svg+xml">
</head>
<body>
<div class="auth-wrap">
    <div class="auth-card">@yield('content')</div>
</div>
</body>
</html>
