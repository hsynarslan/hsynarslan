<!doctype html>
<html lang="{{ app()->getLocale() }}">
<head><meta charset="utf-8"><meta name="viewport" content="width=device-width, initial-scale=1"><title>@yield('code')</title>
<link rel="stylesheet" href="{{ asset('css/app.css') }}?v=2"></head>
<body class="guest"><div class="error-page"><div class="card error-card">
<span class="error-code">@yield('code')</span><p class="lead">@yield('message')</p><a class="btn btn-primary" href="{{ url('/') }}">{{ __('Ana sayfa') }}</a>
</div></div></body></html>
