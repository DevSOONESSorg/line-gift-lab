@extends('layouts.admin')
@section('title', '商品')
@section('content')
<p><a href="{{ route('admin.stores.show', $store) }}">← {{ $store->name }}</a></p>
<h1 class="h3 mb-3">{{ $menu->exists ? '商品を編集' : '商品を追加' }}</h1>
<form method="post" action="{{ $menu->exists ? route('admin.stores.menus.update', [$store, $menu]) : route('admin.stores.menus.store', $store) }}" class="card card-body" style="max-width:560px">
  @csrf @if ($menu->exists) @method('PUT') @endif
  <label class="form-label">テンプレートから選ぶ（任意）</label>
  <select name="menu_template_id" class="form-select mb-3" onchange="const o=this.selectedOptions[0]; if(o.dataset.name){ name.value=o.dataset.name; price.value=o.dataset.price; }">
    <option value="">使わない</option>
    @foreach ($templates as $t)<option value="{{ $t->id }}" data-name="{{ $t->name }}" data-price="{{ $t->reference_price }}" @selected(old('menu_template_id', $menu->menu_template_id) == $t->id)>{{ $t->category }}：{{ $t->name }}（参考 ¥{{ number_format($t->reference_price) }}）</option>@endforeach
  </select>
  <label class="form-label">メニュー名</label><input id="name" name="name" class="form-control mb-3" value="{{ old('name', $menu->name) }}">
  <label class="form-label">価格（円）</label><input id="price" name="price" type="number" class="form-control mb-3" value="{{ old('price', $menu->price) }}">
  <div class="form-check mb-3"><input type="checkbox" class="form-check-input" name="is_active" value="1" id="act" @checked(old('is_active', $menu->is_active))><label for="act" class="form-check-label">販売中</label></div>
  <button class="btn btn-primary">保存</button>
</form>
@if ($menu->exists)
  <form method="post" action="{{ route('admin.stores.menus.destroy', [$store, $menu]) }}" class="mt-3" data-confirm="この商品を削除しますか？">@csrf @method('DELETE')<button class="btn btn-sm btn-outline-danger">削除</button></form>
@endif
@endsection
