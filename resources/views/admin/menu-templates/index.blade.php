@extends('layouts.admin')
@section('title', '商品テンプレート')
@section('content')
<div class="d-flex justify-content-between align-items-center mb-3"><h1 class="h3 mb-0">商品テンプレート</h1><a class="btn btn-primary" href="{{ route('admin.menu-templates.create') }}">新規作成</a></div>
<p class="small text-muted">運営が用意する「よくある商品」の見本です。オーナーは商品登録のときに、ここから選んで値段だけ変えられます。</p>
<form class="row g-2 mb-3"><div class="col-auto"><select name="category" class="form-select"><option value="">全てのカテゴリ</option>
  @foreach ($categories as $c)<option @selected(request('category') === $c)>{{ $c }}</option>@endforeach</select></div><div class="col-auto"><button class="btn btn-secondary">検索</button></div></form>
<div class="card"><table class="table table-hover mb-0 align-middle"><thead><tr><th>並び順</th><th>画像</th><th>テンプレート名</th><th>カテゴリ</th><th>参考価格</th><th>状態</th><th>操作</th></tr></thead><tbody>
@foreach ($templates as $t)
  <tr><td>{{ $t->sort_order }}</td><td><span class="swatch" style="background:{{ $t->image_color }}"></span></td>
    <td><strong>{{ $t->name }}</strong><div class="small text-muted">{{ $t->description }}</div></td><td>{{ $t->category }}</td><td>¥{{ number_format($t->reference_price) }}</td>
    <td><span class="badge text-bg-{{ $t->is_active ? 'success' : 'secondary' }}">{{ $t->is_active ? '有効' : '無効' }}</span></td>
    <td class="text-nowrap"><a class="btn btn-sm btn-outline-primary" href="{{ route('admin.menu-templates.edit', $t) }}">編集</a>
      <form method="post" action="{{ route('admin.menu-templates.destroy', $t) }}" class="d-inline" data-confirm="「{{ $t->name }}」を削除しますか？">@csrf @method('DELETE')<button class="btn btn-sm btn-outline-danger">削除</button></form></td></tr>
@endforeach
</tbody></table></div>
@endsection
