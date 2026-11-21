@extends('layouts.app')
@section('title', __('Tesisler'))
@section('content')
<div class="page-head"><h1>{{ __('Tesisler') }}</h1><a class="btn btn-primary" href="{{ route('admin.facilities.create') }}">+ {{ __('Yeni tesis') }}</a></div>
<div class="card table-wrap">
<table class="table">
    <thead><tr><th>{{ __('Tesis') }}</th><th>{{ __('Slot') }}</th><th>{{ __('Kapasite') }}</th><th>{{ __('Grup') }}</th><th>{{ __('Birim') }}</th><th>{{ __('Aktivite') }}</th><th>{{ __('Onaycı') }}</th><th>{{ __('Durum') }}</th><th></th></tr></thead>
    <tbody>
    @foreach ($facilities as $f)
        <tr>
            <td><strong>{{ $f->name }}</strong></td><td>{{ $f->slot_minutes }} {{ __('dk') }}</td><td>{{ $f->capacity }}</td>
            <td>{{ $f->group_allowed ? ($f->participant_list_required ? __('Açık, liste zorunlu') : __('Açık')) : __('Kapalı') }}</td>
            <td>{{ $f->units_count }}</td><td>{{ $f->activities_count }}</td>
            <td>@if ($f->approvers_count){{ $f->approvers_count }}@else<span class="badge badge-pending">{{ __('Yok – yalnızca SKS Admin') }}</span>@endif</td>
            <td><span class="badge {{ $f->is_active ? 'badge-ok' : '' }}">{{ $f->is_active ? __('Aktif') : __('Pasif') }}</span></td>
            <td class="actions"><a class="btn btn-sm" href="{{ route('admin.facilities.edit', $f) }}">{{ __('Düzenle') }}</a>
                @if ($f->usesDynamicQr())<a class="btn btn-sm" href="{{ route('kiosk.show', $f) }}">{{ __('Tablet') }}</a>@else<a class="btn btn-sm" href="{{ route('admin.facilities.qr', $f) }}">QR</a>@endif</td>
        </tr>
    @endforeach
    </tbody>
</table>
</div>
@endsection
