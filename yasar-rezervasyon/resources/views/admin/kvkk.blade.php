@extends('layouts.app')
@section('title', __('KVKK Metni'))
@section('content')
<div class="page-head"><h1>{{ __('KVKK Aydınlatma Metni') }}</h1></div>
<div class="card table-wrap"><table class="table">
    <thead><tr><th>{{ __('Sürüm') }}</th><th>{{ __('Yayım') }}</th><th>{{ __('Onaylayan') }}</th></tr></thead>
    <tbody>@forelse ($texts as $t)<tr><td>{{ $t->version }} @if ($current?->is($t))<span class="badge badge-ok">{{ __('Güncel') }}</span>@endif</td>
        <td>{{ $t->published_at->format('d.m.Y H:i') }}</td><td>{{ $t->consents_count }} / {{ $userCount }}</td></tr>
    @empty<tr><td colspan="3" class="muted">{{ __('Henüz metin yayımlanmadı. Metin yayımlanana kadar kullanıcılardan onay istenmez.') }}</td></tr>@endforelse</tbody>
</table></div>
<form class="card" method="post" action="{{ route('admin.kvkk.store') }}">@csrf
    <h2 class="mt-0">{{ __('Yeni sürüm yayımla') }}</h2>
    <p class="hint">{{ __('Metin Hukuk Birimi tarafından hazırlanmalıdır. Yayımlandığında tüm kullanıcılardan yeniden onay istenir.') }}</p>
    <div class="field"><label for="k-v">{{ __('Sürüm') }}</label><input id="k-v" type="text" name="version" required maxlength="20" value="{{ old('version') }}" placeholder="1.1"></div>
    <div class="field"><label for="k-tr">{{ __('Metin (TR)') }}</label><textarea id="k-tr" name="body_tr" required rows="10">{{ old('body_tr', $current?->body_tr) }}</textarea></div>
    <div class="field"><label for="k-en">{{ __('Metin (EN)') }}</label><textarea id="k-en" name="body_en" required rows="10">{{ old('body_en', $current?->body_en) }}</textarea></div>
    <button class="btn btn-primary" type="submit" data-confirm="{{ __('Yayımlansın mı?') }}">{{ __('Yayımla') }}</button>
</form>
@endsection
