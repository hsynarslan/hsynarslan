@extends('layouts.app')
@section('title', __('Ekipman'))
@section('content')
<div class="page-head"><h1>{{ __('Ekipman') }}</h1></div>
@foreach ($items->push(new \App\Models\Equipment(['is_active' => true])) as $e)
    <form class="card" method="post" action="{{ $e->exists ? route('admin.equipment.update', $e) : route('admin.equipment.store') }}">@csrf @if ($e->exists) @method('put') @else <strong>+ {{ __('Yeni ekipman') }}</strong> @endif
        <div class="form-row">
            <div class="field"><label for="e{{ $e->id }}tr">{{ __('Ad (TR)') }}</label><input id="e{{ $e->id }}tr" type="text" name="name_tr" required maxlength="150" value="{{ $e->name_tr }}"></div>
            <div class="field"><label for="e{{ $e->id }}en">{{ __('Ad (EN)') }}</label><input id="e{{ $e->id }}en" type="text" name="name_en" required maxlength="150" value="{{ $e->name_en }}"></div>
            <div class="field"><label for="e{{ $e->id }}s">{{ __('Toplam stok') }}</label><input id="e{{ $e->id }}s" type="number" min="0" name="total_stock" required value="{{ $e->total_stock ?? 0 }}"></div>
        </div>
        <div class="checks">
            @foreach ($facilities as $f)<label class="check"><input type="checkbox" name="facilities[]" value="{{ $f->id }}" @checked($e->exists && $e->facilities->contains($f))> {{ $f->name }}</label>@endforeach
            @if ($e->exists)<label class="check"><input type="checkbox" name="is_active" value="1" @checked($e->is_active)> <strong>{{ __('Aktif') }}</strong></label>@endif
        </div>
        <button class="btn btn-sm mt" type="submit">{{ __('Kaydet') }}</button>
    </form>
@endforeach
@endsection
