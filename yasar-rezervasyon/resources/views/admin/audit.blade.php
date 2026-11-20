@extends('layouts.app')
@section('title', __('İşlem Kaydı'))
@section('content')
<div class="page-head"><h1>{{ __('İşlem Kaydı') }}</h1>
    <a class="btn" href="{{ route('admin.audit.index', ['verify' => 1]) }}">{{ __('Zinciri doğrula') }}</a></div>
@if ($broken === 0)<div class="alert alert-success">{{ __('İşlem kaydı zinciri sağlam. Kayıtlarda değişiklik veya silme tespit edilmedi.') }}</div>
@elseif ($broken)<div class="alert alert-danger">{{ __('UYARI: İşlem kaydı zinciri #:id numaralı kayıtta bozuk. Bilgi Güvenliği birimine bildirin.', ['id' => $broken]) }}</div>@endif
<form class="card form-row" method="get">
    <div class="field"><label for="au-a">{{ __('İşlem') }}</label><input id="au-a" type="text" name="action" value="{{ request('action') }}" placeholder="reservation."></div>
    <div class="field"><label for="au-u">{{ __('Kullanıcı (ad / no)') }}</label><input id="au-u" type="text" name="user" value="{{ request('user') }}"></div>
    <div class="field"><label for="au-f">{{ __('Başlangıç') }}</label><input id="au-f" type="date" name="from" value="{{ request('from') }}"></div>
    <div class="field"><label for="au-t">{{ __('Bitiş') }}</label><input id="au-t" type="date" name="to" value="{{ request('to') }}"></div>
    <div class="field"><label>&nbsp;</label><button class="btn" type="submit">{{ __('Filtrele') }}</button></div>
</form>
<div class="card table-wrap"><table class="table">
    <thead><tr><th>#</th><th>{{ __('Zaman') }}</th><th>{{ __('Kullanıcı') }}</th><th>{{ __('İşlem') }}</th><th>{{ __('Kayıt') }}</th><th>{{ __('Ayrıntı') }}</th><th>IP</th></tr></thead>
    <tbody>@foreach ($logs as $l)
        <tr><td>{{ $l->id }}</td><td class="nowrap">{{ $l->created_at->format('d.m.Y H:i:s') }}</td><td>{{ $l->user?->name ?? __('Sistem') }}</td>
            <td><code>{{ $l->action }}</code></td><td>{{ $l->subject_type }} {{ $l->subject_id }}</td>
            <td class="mono">{{ $l->data ? \Illuminate\Support\Str::limit(json_encode($l->data, JSON_UNESCAPED_UNICODE), 160) : '' }}</td><td class="small">{{ $l->ip_address }}</td></tr>
    @endforeach</tbody>
</table></div>
{{ $logs->links() }}
@endsection
