@php($loc = app()->getLocale())
<article class="res s-{{ $r->status }}">
    <div class="res-date">
        <small>{{ $r->starts_at->locale($loc)->isoFormat('MMM') }}</small>
        <b>{{ $r->starts_at->format('j') }}</b>
        <span>{{ $r->starts_at->locale($loc)->isoFormat('ddd') }}</span>
    </div>
    <div class="res-main">
        <div class="res-title">
            <strong>{{ $r->facility->name }}</strong>
            <span class="muted">{{ $r->title() }}</span>
        </div>
        <div class="res-meta">
            <span>@icon('clock', 15) {{ $r->starts_at->format('H:i') }}–{{ $r->ends_at->format('H:i') }}</span>
            <span>@icon('map-pin', 15) {{ $r->units->map->name->join(', ') }}</span>
            <span>@icon('users', 15) {{ trans_choice(':count kişi', $r->participant_count) }}</span>
            @if ($r->team)<span>@icon('trophy', 15) {{ $r->team->name }}</span>@endif
            @if (isset($participating))<span>@icon('user', 15) {{ $r->user?->name }}</span>@endif
        </div>
        @if ($r->equipmentItems->isNotEmpty() || ($r->participantUsers->isNotEmpty() && ! isset($participating)) || $r->rejection_reason || $r->cancel_reason)
            <div class="res-extra">
                @if ($r->equipmentItems->isNotEmpty())<div>@icon('package', 15) {{ $r->equipmentItems->map(fn ($e) => $e->quantity.' × '.$e->equipment->name)->join(', ') }}</div>@endif
                @if ($r->participantUsers->isNotEmpty() && ! isset($participating))<div>@icon('users', 15) {{ $r->participantUsers->pluck('name')->join(', ') }}</div>@endif
                @if ($r->rejection_reason)<div class="reason">{{ __('Red gerekçesi') }}: {{ $r->rejection_reason }}</div>@endif
                @if ($r->cancel_reason)<div class="reason">{{ __('İptal gerekçesi') }}: {{ $r->cancel_reason }}</div>@endif
            </div>
        @endif
        <div class="res-code">{{ $r->code }} · {{ $r->kindLabel() }}</div>
    </div>
    <div class="res-side">
        <div class="actions">
            <span class="badge badge-{{ $r->status }}">{{ $r->statusLabel() }}</span>
            @if ($r->attendance)<span class="badge badge-{{ $r->attendance }}">{{ $r->attendance === 'attended' ? __('Geldi') : __('Gelmedi') }}</span>@endif
            @if ($r->is_late_cancel)<span class="badge badge-danger">{{ __('Geç iptal') }}</span>@endif
        </div>
        @if (isset($participating))
            @if ($r->isActive() && $r->starts_at->isFuture())
                <form method="post" action="{{ route('reservations.leave', $r) }}" data-confirm="{{ __('Katılımcı listesinden çıkmak istiyor musunuz?') }}">@csrf
                    <button class="btn btn-sm btn-danger" type="submit">{{ __('Listeden çık') }}</button></form>
            @endif
        @elseif ($r->isActive() && $r->starts_at->isFuture())
            @php($late = $r->status === 'approved' && now()->gt($r->cancelDeadline()))
            <form method="post" action="{{ route('reservations.cancel', $r) }}" data-confirm="{{ $late ? __('İptal süresi geçti. Bu iptal GEÇ İPTAL sayılacak ve yaptırıma yol açabilir. Devam edilsin mi?') : __('Rezervasyonu iptal etmek istiyor musunuz?') }}">@csrf
                <button class="btn btn-sm btn-danger" type="submit">@icon('x', 15) {{ $r->status === 'pending' ? __('Geri çek') : __('İptal et') }}</button></form>
        @endif
    </div>
</article>
