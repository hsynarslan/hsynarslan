@extends('layouts.app')
@section('title', __('Kullanıcılar'))
@section('content')
<div class="page-head"><h1>{{ __('Kullanıcılar') }}</h1></div>
<div class="grid grid-2">
    <form class="card form-row" method="get">
        <div class="field"><label for="q">{{ __('Ara (ad, numara, kullanıcı adı)') }}</label><input id="q" type="search" name="q" value="{{ $q }}"></div>
        <div class="field"><label for="flt">{{ __('Filtre') }}</label><select id="flt" name="filter"><option value="">{{ __('Tümü') }}</option>
            <option value="privileged" @selected($filter === 'privileged')>{{ __('Yetkili roller') }}</option><option value="sanctioned" @selected($filter === 'sanctioned')>{{ __('Yaptırımlı') }}</option></select></div>
        <div class="field"><label>&nbsp;</label><button class="btn" type="submit">{{ __('Ara') }}</button></div>
    </form>
    <form class="card form-row" method="post" action="{{ route('admin.users.import') }}">@csrf
        <div class="field"><label for="imp">{{ __('LDAP\'tan içe aktar (numara / kullanıcı adı)') }}</label><input id="imp" type="text" name="number" required maxlength="100">
            <div class="hint">{{ __('Henüz giriş yapmamış birine rol vermek veya onaycı atamak için.') }}</div></div>
        <div class="field"><label>&nbsp;</label><button class="btn" type="submit">{{ __('İçe aktar') }}</button></div>
    </form>
</div>
<div class="alert alert-info small">{{ __('Yetki değişiklikleri işlem kaydına yazılır. En az yetki ilkesine göre yalnızca gereken rolleri verin ve görevi biten kişilerin yetkilerini kaldırın.') }}</div>
<div class="card table-wrap"><table class="table">
    <thead><tr><th>{{ __('Kullanıcı') }}</th><th>{{ __('LDAP rolü') }}</th><th>{{ __('Sistem rolleri') }}</th><th>{{ __('Yaptırım') }}</th></tr></thead>
    <tbody>@foreach ($users as $user)
        <tr>
            <td><strong>{{ $user->name }}</strong><div class="small muted">{{ $user->username }} · {{ $user->number }} · {{ $user->email }}</div>
                <div class="small muted">{{ __('Son giriş') }}: {{ $user->last_login_at?->format('d.m.Y H:i') ?? '—' }}</div></td>
            <td>{{ \App\Support\Roles::label($user->base_role) }}</td>
            <td><form method="post" action="{{ route('admin.users.update', $user) }}">@csrf @method('put')
                <div class="checks">
                    <label class="check"><input type="checkbox" name="is_coach" value="1" @checked($user->is_coach)> {{ __('Antrenör') }}</label>
                    <label class="check"><input type="checkbox" name="is_attendant" value="1" @checked($user->is_attendant)> {{ __('Salon Görevlisi') }}</label>
                    <label class="check"><input type="checkbox" name="is_sks_admin" value="1" @checked($user->is_sks_admin)> {{ __('SKS Admin') }}</label>
                    <label class="check"><input type="checkbox" name="is_active" value="1" @checked($user->is_active)> {{ __('Aktif') }}</label>
                </div><button class="btn btn-sm" type="submit">{{ __('Kaydet') }}</button></form></td>
            <td>
                @foreach ($user->sanctions as $s)
                    <div class="small"><span class="badge badge-danger">{{ $s->ends_at->format('d.m.Y') }}</span> {{ $s->reason }}
                        <form class="inline-form" method="post" action="{{ route('admin.sanctions.lift', $s) }}">@csrf<button class="btn btn-sm" type="submit">{{ __('Kaldır') }}</button></form></div>
                @endforeach
                <details class="inline-details"><summary class="small">{{ __('Yaptırım ver') }}</summary>
                    <form class="details-body" method="post" action="{{ route('admin.users.sanction', $user) }}">@csrf
                        <label for="sd{{ $user->id }}">{{ __('Gün') }}</label><input id="sd{{ $user->id }}" type="number" name="days" min="1" max="365" value="14" required>
                        <label for="sr{{ $user->id }}">{{ __('Gerekçe') }}</label><input id="sr{{ $user->id }}" type="text" name="reason" required maxlength="500">
                        <button class="btn btn-sm btn-danger mt" type="submit">{{ __('Uygula') }}</button></form></details>
            </td>
        </tr>
    @endforeach</tbody>
</table></div>
{{ $users->links() }}
@endsection
