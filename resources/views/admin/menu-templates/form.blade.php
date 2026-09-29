@extends('layouts.admin')
@section('title', '商品テンプレート')
@section('content')
<p><a href="{{ route('admin.menu-templates.index') }}">← 商品テンプレート</a></p>
<h1 class="h3 mb-3">{{ $template->exists ? '編集' : '新規作成' }}</h1>
<form method="post" action="{{ $template->exists ? route('admin.menu-templates.update', $template) : route('admin.menu-templates.store') }}" class="card card-body" style="max-width:600px">
  @csrf @if ($template->exists) @method('PUT') @endif
  <label class="form-label">並び順</label><input name="sort_order" type="number" class="form-control mb-2" value="{{ old('sort_order', $template->sort_order ?? 0) }}">
  <label class="form-label">テンプレート名</label><input name="name" class="form-control mb-2" value="{{ old('name', $template->name) }}">
  <label class="form-label">説明</label><input name="description" class="form-control mb-2" value="{{ old('description', $template->description) }}">
  <label class="form-label">カテゴリ</label><select name="category" class="form-select mb-2">@foreach ($categories as $c)<option @selected(old('category', $template->category) === $c)>{{ $c }}</option>@endforeach</select>
  <label class="form-label">参考価格（円）</label><input name="reference_price" type="number" class="form-control mb-2" value="{{ old('reference_price', $template->reference_price) }}">
  <label class="form-label">画像の代わりの色</label><input name="image_color" type="color" class="form-control form-control-color mb-2" value="{{ old('image_color', $template->image_color) }}">
  <div class="form-check mb-3"><input type="checkbox" class="form-check-input" name="is_active" value="1" id="a" @checked(old('is_active', $template->is_active))><label class="form-check-label" for="a">有効</label></div>
  <button class="btn btn-primary">保存</button>
</form>
@endsection
