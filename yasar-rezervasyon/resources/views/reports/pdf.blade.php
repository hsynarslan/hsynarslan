<!doctype html>
<html lang="{{ app()->getLocale() }}"><head><meta charset="utf-8">
<style>
    body { font-family: DejaVu Sans, sans-serif; font-size: 10px; color: #1b2333; }
    h1 { font-size: 16px; margin: 0 0 4px; } h2 { font-size: 12px; margin: 14px 0 4px; color: #1d3b6e; } h3 { font-size: 11px; margin: 8px 0 4px; }
    table { width: 100%; border-collapse: collapse; margin-bottom: 6px; } th, td { border: 1px solid #dfe3ec; padding: 3px 4px; text-align: left; }
    th { background: #f4f6fa; } .heat td { text-align: center; } .h1 { background: #dbe6f7; } .h2 { background: #aec6ee; } .h3 { background: #6f97d6; color: #fff; } .h4 { background: #1d3b6e; color: #fff; }
</style></head>
<body>
<h1>{{ __('Yaşar Üniversitesi Tesis Rezervasyon Raporu') }}</h1>
<div>{{ $from->format('d.m.Y') }} – {{ $to->format('d.m.Y') }} · {{ __('Oluşturma') }}: {{ now()->format('d.m.Y H:i') }} · {{ auth()->user()->name }}</div>
@include('reports._tables')
</body></html>
