@extends('layouts.admin')
@section('title', '代理店管理')
@section('content')
<div class="d-flex justify-content-between align-items-center mb-3"><h1 class="h3 mb-0">代理店管理</h1><a class="btn btn-primary" href="{{ route('admin.agents.create') }}">新規登録</a></div>
<form class="row g-2 mb-3"><div class="col-auto"><input name="q" class="form-control" placeholder="代理店名・紹介コード" value="{{ request('q') }}"></div>
  <div class="col-auto"><button class="btn btn-secondary">検索</button> <a class="btn btn-outline-secondary" href="{{ route('admin.agents.index') }}">リセット</a></div></form>
<div class="card"><table class="table table-hover mb-0 align-middle"><thead><tr><th>ID</th><th>代理店名</th><th>紹介コード</th><th>階層</th><th>報酬率</th><th>店舗数</th><th>ステータス</th><th>登録日</th><th>操作</th></tr></thead><tbody>
@forelse ($agents as $a)
  <tr><td>{{ $a->id }}</td><td>{{ $a->parent_id ? '　└ ' : '' }}{{ $a->name }} @if ($a->company)<div class="small text-muted">({{ $a->company }})</div>@endif</td>
    <td><code>{{ $a->referral_code }}</code></td><td>{{ $a->parent_id ? '2次（親：'.$a->parent->name.'）' : '1次' }}</td>
    <td>{{ $a->parent_id ? '—' : rtrim(rtrim($a->reward_rate, '0'), '.').'%' }}</td><td>{{ $a->stores_count }}</td>
    <td><span class="badge text-bg-{{ $a->is_active ? 'success' : 'secondary' }}">{{ $a->is_active ? '有効' : '無効' }}</span></td><td class="small">{{ $a->created_at->format('Y/m/d') }}</td>
    <td class="text-nowrap"><a class="btn btn-sm btn-outline-secondary" href="{{ route('admin.agents.show', $a) }}">詳細</a> <a class="btn btn-sm btn-outline-primary" href="{{ route('admin.agents.edit', $a) }}">編集</a></td></tr>
@empty
  <tr><td colspan="9" class="text-center text-muted py-4">代理店はありません</td></tr>
@endforelse
</tbody></table></div>
<p class="small text-muted mt-2">2次代理店の報酬率は表示しません（仕様）。2次の取り分は1次代理店から受け取るため、運営は1次代理店にだけ報酬を支払います。</p>
@endsection
