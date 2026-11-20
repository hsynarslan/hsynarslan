@extends('layouts.app')
@section('title', __('Ayarlar'))
@section('content')
@php($labels = [
    'default_max_duration_minutes' => 'Varsayılan en uzun süre (dakika)', 'default_max_per_day' => 'Varsayılan günlük limit (tesis başına)',
    'default_max_per_week' => 'Varsayılan haftalık limit (tesis başına)', 'default_max_days_ahead' => 'Varsayılan en ileri tarih (gün)',
    'default_min_hours_before' => 'Varsayılan en geç talep (saat önce)', 'approver_reminder_after_hours' => 'Onaycı hatırlatması: talepten kaç saat sonra',
    'approver_reminder_before_start_hours' => 'Onaycı hatırlatması: başlangıca kaç saat kala', 'noshow_grace_minutes' => 'Gelmedi sayılma süresi (dakika)',
    'checkin_early_minutes' => 'En erken check-in (başlangıçtan kaç dk önce)', 'sanction_threshold' => 'Yaptırım eşiği (gelmeme + geç iptal)',
    'sanction_window_days' => 'Yaptırım penceresi (gün)', 'sanction_duration_days' => 'Yaptırım süresi (gün)',
    'retention_years' => 'Rezervasyon saklama süresi (yıl)', 'health_warning_days' => 'Sağlık raporu uyarısı (kaç gün kala)',
])
<div class="page-head"><h1>{{ __('Ayarlar') }}</h1></div>
<form class="card" method="post" action="{{ route('admin.settings.update') }}">@csrf @method('put')
    <div class="grid grid-2">
    @foreach ($values as $k => $v)
        <div class="field"><label for="s-{{ $k }}">{{ __($labels[$k]) }}</label><input id="s-{{ $k }}" type="number" min="0" name="{{ $k }}" value="{{ $v }}" required></div>
    @endforeach
    </div>
    <button class="btn btn-primary" type="submit">{{ __('Kaydet') }}</button>
</form>
@endsection
