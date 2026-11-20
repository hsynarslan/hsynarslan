<x-mail::message>
@foreach ($lines as $line)
{{ $line }}

@endforeach
@if ($reservation)
<x-mail::table>
| | |
|:--|:--|
| {{ __('Kod') }} | {{ $reservation->code }} |
| {{ __('Tesis') }} | {{ $reservation->facility->name }} |
| {{ __('Aktivite') }} | {{ $reservation->title() }} |
| {{ __('Tarih') }} | {{ $reservation->starts_at->locale(app()->getLocale())->isoFormat('LL dddd') }} |
| {{ __('Saat') }} | {{ $reservation->starts_at->format('H:i') }}–{{ $reservation->ends_at->format('H:i') }} |
| {{ __('Alan') }} | {{ $reservation->units->map->name->join(', ') }} |
@if ($reservation->team)
| {{ __('Takım') }} | {{ $reservation->team->name }} |
@endif
@foreach ($reservation->equipmentItems as $item)
| {{ __('Ekipman') }} | {{ $item->quantity }} × {{ $item->equipment->name }} |
@endforeach
</x-mail::table>
@endif
@if ($actionUrl)
<x-mail::button :url="$actionUrl">
{{ $actionText }}
</x-mail::button>
@endif

{{ __('Bu e-posta otomatik olarak gönderilmiştir.') }}<br>
{{ __('Yaşar Üniversitesi Sağlık, Kültür ve Spor Daire Başkanlığı') }}
</x-mail::message>
