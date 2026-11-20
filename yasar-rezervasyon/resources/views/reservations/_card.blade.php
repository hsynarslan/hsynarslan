<article class="card res-card">
    <div>
        <div class="actions">
            <strong>{{ $r->facility->name }} · {{ $r->title() }}</strong>
            <span class="badge badge-{{ $r->status }}">{{ $r->statusLabel() }}</span>
            <span class="badge">{{ $r->kindLabel() }}</span>
            @if ($r->attendance)<span class="badge badge-{{ $r->attendance }}">{{ $r->attendance === 'attended' ? __('Geldi') : __('Gelmedi') }}</span>@endif
            @if ($r->is_late_cancel)<span class="badge badge-danger">{{ __('Geç iptal') }}</span>@endif
        </div>
        <div class="res-meta">
            <span>{{ $r->starts_at->locale(app()->getLocale())->isoFormat('dddd, D MMMM YYYY') }}</span>
            <span class="nowrap">{{ $r->starts_at->format('H:i') }}–{{ $r->ends_at->format('H:i') }}</span>
            <span>{{ $r->units->map->name->join(', ') }}</span>
            <span>{{ trans_choice(':count kişi', $r->participant_count) }}</span>
            @if ($r->team)<span>{{ __('Takım') }}: {{ $r->team->name }}</span>@endif
            @if (isset($participating))<span>{{ __('Rezervasyonu yapan') }}: {{ $r->user?->name }}</span>@endif
        </div>
        @if ($r->equipmentItems->isNotEmpty())
            <div class="small muted">{{ __('Ekipman') }}: {{ $r->equipmentItems->map(fn ($e) => $e->quantity.' × '.$e->equipment->name)->join(', ') }}</div>
        @endif
        @if ($r->participantUsers->isNotEmpty() && ! isset($participating))
            <div class="small muted">{{ __('Katılımcılar') }}: {{ $r->participantUsers->pluck('name')->join(', ') }}</div>
        @endif
        @if ($r->rejection_reason)<div class="small error">{{ __('Red gerekçesi') }}: {{ $r->rejection_reason }}</div>@endif
        @if ($r->cancel_reason)<div class="small error">{{ __('İptal gerekçesi') }}: {{ $r->cancel_reason }}</div>@endif
        <div class="res-code">{{ $r->code }}</div>
    </div>
    <div class="actions">
        @if (isset($participating))
            @if ($r->isActive() && $r->starts_at->isFuture())
                <form method="post" action="{{ route('reservations.leave', $r) }}" data-confirm="{{ __('Katılımcı listesinden çıkmak istiyor musunuz?') }}">@csrf
                    <button class="btn btn-sm btn-danger" type="submit">{{ __('Listeden çık') }}</button></form>
            @endif
        @elseif ($r->isActive() && $r->starts_at->isFuture())
            @php($late = $r->status === 'approved' && now()->gt($r->cancelDeadline()))
            <form method="post" action="{{ route('reservations.cancel', $r) }}" data-confirm="{{ $late ? __('İptal süresi geçti. Bu iptal GEÇ İPTAL sayılacak ve yaptırıma yol açabilir. Devam edilsin mi?') : __('Rezervasyonu iptal etmek istiyor musunuz?') }}">@csrf
                <button class="btn btn-sm btn-danger" type="submit">{{ $r->status === 'pending' ? __('Geri çek') : __('İptal et') }}</button></form>
        @endif
    </div>
</article>
