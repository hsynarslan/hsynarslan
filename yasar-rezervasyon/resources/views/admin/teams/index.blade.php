@extends('layouts.app')
@section('title', __('Takımlar'))
@section('content')
<div class="page-head"><h1>{{ __('Takımlar') }}</h1></div>
@if ($coaches->isEmpty())<div class="alert alert-warning">{{ __('Henüz antrenör rolü verilmiş kullanıcı yok. Kullanıcılar sayfasından antrenör atayın.') }}</div>@endif
@foreach ($teams as $t)
    <form class="card form-row" method="post" action="{{ route('admin.teams.update', $t) }}">@csrf @method('put')
        <div class="field"><label for="t{{ $t->id }}n">{{ __('Takım adı') }}</label><input id="t{{ $t->id }}n" type="text" name="name" required maxlength="150" value="{{ $t->name }}"></div>
        <div class="field"><label for="t{{ $t->id }}s">{{ __('Branş') }}</label><input id="t{{ $t->id }}s" type="text" name="sport" maxlength="100" value="{{ $t->sport }}"></div>
        <div class="field"><label for="t{{ $t->id }}c">{{ __('Antrenör') }}</label><select id="t{{ $t->id }}c" name="coach_id"><option value="">—</option>@foreach ($coaches as $c)<option value="{{ $c->id }}" @selected($t->coach_id === $c->id)>{{ $c->name }}</option>@endforeach</select></div>
        <div class="field"><label>&nbsp;</label><label class="check"><input type="checkbox" name="is_active" value="1" @checked($t->is_active)> {{ __('Aktif') }}</label></div>
        <div class="field"><label>&nbsp;</label><div class="actions"><button class="btn btn-sm" type="submit">{{ __('Kaydet') }}</button>
            <a class="btn btn-sm" href="{{ route('teams.show', $t) }}">{{ trans_choice(':count oyuncu', $t->active_players_count) }}</a></div></div>
    </form>
@endforeach
<form class="card form-row" method="post" action="{{ route('admin.teams.store') }}">@csrf
    <div class="field"><label for="tn">{{ __('Yeni takım adı') }}</label><input id="tn" type="text" name="name" required maxlength="150"></div>
    <div class="field"><label for="ts">{{ __('Branş') }}</label><input id="ts" type="text" name="sport" maxlength="100"></div>
    <div class="field"><label for="tc">{{ __('Antrenör') }}</label><select id="tc" name="coach_id"><option value="">—</option>@foreach ($coaches as $c)<option value="{{ $c->id }}">{{ $c->name }}</option>@endforeach</select></div>
    <div class="field"><label>&nbsp;</label><button class="btn btn-primary" type="submit">+ {{ __('Takım ekle') }}</button></div>
</form>
@endsection
