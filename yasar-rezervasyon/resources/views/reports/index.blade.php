@extends('layouts.app')
@section('title', __('Raporlar'))
@section('content')
<div class="page-head"><h1>{{ __('Raporlar') }}</h1>
    <div class="actions">
        <a class="btn" href="{{ route('reports.export', ['format' => 'xlsx'] + request()->query()) }}">{{ __('Excel indir') }}</a>
        <a class="btn" href="{{ route('reports.export', ['format' => 'pdf'] + request()->query()) }}">{{ __('PDF indir') }}</a>
    </div>
</div>
<form method="get" class="card form-row">
    <div class="field"><label for="r-from">{{ __('Başlangıç') }}</label><input id="r-from" type="date" name="from" value="{{ $from->toDateString() }}"></div>
    <div class="field"><label for="r-to">{{ __('Bitiş') }}</label><input id="r-to" type="date" name="to" value="{{ $to->toDateString() }}"></div>
    <div class="field"><label for="r-f">{{ __('Tesis') }}</label>
        <select id="r-f" name="facility"><option value="">{{ __('Tümü') }}</option>@foreach ($facilities as $f)<option value="{{ $f->id }}" @selected($selected == $f->id)>{{ $f->name }}</option>@endforeach</select></div>
    <div class="field"><label>&nbsp;</label><button class="btn btn-primary" type="submit">{{ __('Göster') }}</button></div>
</form>
@php($a = $report['attendance'])
<div class="stats">
    <div class="stat"><b>{{ $a['total'] }}</b><span>{{ __('Toplam talep') }}</span></div>
    <div class="stat"><b>%{{ collect($report['occupancy'])->avg('rate') !== null ? round(collect($report['occupancy'])->avg('rate'), 1) : 0 }}</b><span>{{ __('Ortalama doluluk') }}</span></div>
    <div class="stat"><b>%{{ $a['no_show_rate'] }}</b><span>{{ __('Gelmeme oranı') }}</span></div>
    <div class="stat"><b>{{ $report['approval']['avg_minutes'] ?? '—' }}</b><span>{{ __('Ortalama onay süresi (dk)') }}</span></div>
</div>
<div class="card table-wrap">@include('reports._tables')</div>
@endsection
