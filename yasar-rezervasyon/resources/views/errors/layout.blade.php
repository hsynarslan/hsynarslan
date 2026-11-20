<!doctype html>
<html lang="{{ app()->getLocale() }}">
<head><meta charset="utf-8"><meta name="viewport" content="width=device-width, initial-scale=1"><title>@yield('code')</title>
<link rel="stylesheet" href="{{ asset('css/app.css') }}"></head>
<body><div class="auth-wrap"><div class="auth-card"><div class="card">
<h1>@yield('code')</h1><p>@yield('message')</p><a class="btn btn-primary" href="{{ url('/') }}">{{ __('Ana sayfa') }}</a>
</div></div></div></body></html>
