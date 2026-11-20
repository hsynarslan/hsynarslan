@php($r = $report)
<h2>{{ __('Doluluk') }}</h2>
<table class="table">
    <thead><tr><th>{{ __('Tesis') }}</th><th>{{ __('Rezervasyon') }}</th><th>{{ __('Dolu saat') }}</th><th>{{ __('Açık saat') }}</th><th>{{ __('Doluluk %') }}</th></tr></thead>
    <tbody>@foreach ($r['occupancy'] as $o)<tr><td>{{ $o['facility'] }}</td><td>{{ $o['reservations'] }}</td><td>{{ $o['booked_hours'] }}</td><td>{{ $o['available_hours'] }}</td><td>%{{ $o['rate'] }}</td></tr>@endforeach</tbody>
</table>

<h2>{{ __('Yoğunluk (gün × saat, onaylı rezervasyon)') }}</h2>
@php($max = max(1, collect($r['heatmap'])->flatten()->max() ?? 1))
<table class="table heat">
    <thead><tr><th></th>@for ($h = 7; $h <= 23; $h++)<th>{{ sprintf('%02d', $h) }}</th>@endfor</tr></thead>
    <tbody>
    @foreach (range(1, 7) as $d)
        <tr><th>{{ \Illuminate\Support\Carbon::now()->startOfWeek()->addDays($d - 1)->locale(app()->getLocale())->isoFormat('ddd') }}</th>
        @for ($h = 7; $h <= 23; $h++)
            @php($v = $r['heatmap'][$d][$h] ?? 0)
            <td class="h{{ $v ? min(4, (int) ceil($v / $max * 4)) : 0 }}">{{ $v ?: '' }}</td>
        @endfor</tr>
    @endforeach
    </tbody>
</table>

<h2>{{ __('Kullanıcılar') }}</h2>
<table class="table">
    <thead><tr><th>{{ __('Rol') }}</th><th>{{ __('Rezervasyon') }}</th><th>{{ __('Kişi') }}</th></tr></thead>
    <tbody>@forelse ($r['byRole'] as $x)<tr><td>{{ $x['role'] }}</td><td>{{ $x['count'] }}</td><td>{{ $x['users'] }}</td></tr>@empty<tr><td colspan="3">—</td></tr>@endforelse</tbody>
</table>
<h3>{{ __('En çok rezervasyon yapanlar') }}</h3>
<table class="table">
    <thead><tr><th>{{ __('Ad') }}</th><th>{{ __('Numara') }}</th><th>{{ __('Rezervasyon') }}</th></tr></thead>
    <tbody>@forelse ($r['topUsers'] as $x)<tr><td>{{ $x['name'] }}</td><td>{{ $x['number'] }}</td><td>{{ $x['count'] }}</td></tr>@empty<tr><td colspan="3">—</td></tr>@endforelse</tbody>
</table>

<h2>{{ __('Gelmeme ve iptal') }}</h2>
@php($a = $r['attendance'])
<table class="table">
    <tbody>
    <tr><th>{{ __('Toplam talep') }}</th><td>{{ $a['total'] }}</td><th>{{ __('Gerçekleşen (onaylı)') }}</th><td>{{ $a['approved_past'] }}</td></tr>
    <tr><th>{{ __('Geldi') }}</th><td>{{ $a['attended'] }}</td><th>{{ __('Gelmedi') }}</th><td>{{ $a['no_show'] }} (%{{ $a['no_show_rate'] }})</td></tr>
    <tr><th>{{ __('İptal') }}</th><td>{{ $a['cancelled'] }}</td><th>{{ __('Geç iptal') }}</th><td>{{ $a['late_cancel'] }}</td></tr>
    </tbody>
</table>
<h3>{{ __('Yaptırımlar') }}</h3>
<table class="table">
    <thead><tr><th>{{ __('Ad') }}</th><th>{{ __('Numara') }}</th><th>{{ __('Başlangıç') }}</th><th>{{ __('Bitiş') }}</th><th>{{ __('Gerekçe') }}</th></tr></thead>
    <tbody>@forelse ($r['sanctioned'] as $x)<tr><td>{{ $x['name'] }}</td><td>{{ $x['number'] }}</td><td>{{ $x['from'] }}</td><td>{{ $x['to'] }}@if ($x['lifted']) ({{ __('kaldırıldı') }})@endif</td><td>{{ $x['reason'] }}</td></tr>@empty<tr><td colspan="5">—</td></tr>@endforelse</tbody>
</table>

<h2>{{ __('Onay performansı') }}</h2>
@php($p = $r['approval'])
<table class="table">
    <tbody>
    <tr><th>{{ __('Ortalama onay süresi') }}</th><td>{{ $p['avg_minutes'] !== null ? $p['avg_minutes'].' '.__('dk') : '—' }}</td><th>{{ __('Beklemede') }}</th><td>{{ $p['pending'] }}</td></tr>
    <tr><th>{{ __('Onaylanan') }}</th><td>{{ $p['approved'] }}</td><th>{{ __('Reddedilen') }}</th><td>{{ $p['rejected'] }}</td></tr>
    <tr><th>{{ __('Süresi dolan (onaylanmadan iptal)') }}</th><td>{{ $p['expired'] }}</td><td></td><td></td></tr>
    </tbody>
</table>
<table class="table">
    <thead><tr><th>{{ __('Onaycı') }}</th><th>{{ __('Onay') }}</th><th>{{ __('Red') }}</th></tr></thead>
    <tbody>@forelse ($p['by_approver'] as $x)<tr><td>{{ $x['name'] }}</td><td>{{ $x['approved'] }}</td><td>{{ $x['rejected'] }}</td></tr>@empty<tr><td colspan="3">—</td></tr>@endforelse</tbody>
</table>

<h2>{{ __('Takım kullanımı') }}</h2>
<table class="table">
    <thead><tr><th>{{ __('Takım') }}</th><th>{{ __('Rezervasyon') }}</th><th>{{ __('Saat') }}</th></tr></thead>
    <tbody>@forelse ($r['teams'] as $x)<tr><td>{{ $x['team'] }}</td><td>{{ $x['count'] }}</td><td>{{ $x['hours'] }}</td></tr>@empty<tr><td colspan="3">—</td></tr>@endforelse</tbody>
</table>

<h2>{{ __('Hasarlı / kayıp ekipman') }}</h2>
<table class="table">
    <thead><tr><th>{{ __('Kod') }}</th><th>{{ __('Ekipman') }}</th><th>{{ __('Adet') }}</th><th>{{ __('Durum') }}</th><th>{{ __('Kişi') }}</th><th>{{ __('Not') }}</th></tr></thead>
    <tbody>@forelse ($r['equipment'] as $x)<tr><td>{{ $x['code'] }}</td><td>{{ $x['equipment'] }}</td><td>{{ $x['quantity'] }}</td><td>{{ $x['condition'] }}</td><td>{{ $x['user'] }}</td><td>{{ $x['note'] }}</td></tr>@empty<tr><td colspan="6">—</td></tr>@endforelse</tbody>
</table>
