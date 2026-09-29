@extends('layouts.admin')
@section('title', '店舗管理')
@section('content')
<h1 class="h3 mb-3">店舗管理</h1>
<form class="row g-2 mb-3" method="get">
  <div class="col-auto"><input name="q" class="form-control" placeholder="店舗名・スラッグ" value="{{ request('q') }}"></div>
  <div class="col-auto"><select name="status" class="form-select"><option value="">すべてのステータス</option>
    <option value="pending" @selected(request('status') === 'pending')>未承認</option><option value="approved" @selected(request('status') === 'approved')>承認済み</option></select></div>
  <div class="col-auto"><button class="btn btn-secondary">検索</button></div>
</form>
<div class="card"><div class="table-responsive"><table class="table table-hover mb-0 align-middle">
  <thead><tr><th>ID</th><th>店舗名</th><th>スラッグ</th><th>手数料率</th><th>注文数</th><th>ステータス</th><th>登録日</th><th>操作</th></tr></thead>
  <tbody>
  @forelse ($stores as $s)
    <tr>
      <td>{{ $s->id }}</td>
      <td><a href="{{ route('admin.stores.show', $s) }}">{{ $s->name }}</a></td>
      <td><code>{{ $s->slug }}</code></td>
      <td>{{ rtrim(rtrim($s->commission_rate, '0'), '.') }}%</td>
      <td>{{ $s->orders_count }}</td>
      <td>
        <span class="badge text-bg-{{ $s->is_approved ? 'success' : 'warning' }}">{{ $s->is_approved ? '承認済み' : '未承認' }}</span>
        @if ($s->is_listed_in_directory)<span class="badge text-bg-info">共通掲載</span>@endif
        @if ($s->wants_original || $s->hasOwnLiff())<span class="badge text-bg-secondary">店舗専用</span>@endif
        @if ($s->hasOwnMessagingChannel())<span class="badge text-bg-success">LINE設定済</span>@endif
      </td>
      <td class="small">{{ $s->created_at->format('Y/m/d') }}</td>
      <td class="text-nowrap">
        @if ($s->is_approved)
          {{-- 取り返しのつかない操作には確認を出す（本番の管理画面を見て気づいた改善点） --}}
          <form method="post" action="{{ route('admin.stores.reject', $s) }}" class="d-inline" data-confirm="「{{ $s->name }}」の承認を取り消しますか？お客さんが贈れなくなります。">@csrf<button class="btn btn-sm btn-outline-warning">取消</button></form>
        @else
          <form method="post" action="{{ route('admin.stores.approve', $s) }}" class="d-inline" data-confirm="「{{ $s->name }}」を承認しますか？">@csrf<button class="btn btn-sm btn-success">承認</button></form>
        @endif
        <a class="btn btn-sm btn-outline-primary" href="{{ route('admin.stores.edit', $s) }}">編集</a>
      </td>
    </tr>
  @empty
    <tr><td colspan="8" class="text-center text-muted py-4">店舗はありません</td></tr>
  @endforelse
  </tbody>
</table></div></div>
<div class="mt-3">{{ $stores->links('pagination::bootstrap-5') }}</div>

<details class="card mt-4"><summary class="card-header">＋ 管理画面から店舗を追加する（コースB用）</summary><div class="card-body">
  <p class="small text-muted">本来はオーナーが運営の公式LINEの「出店登録」から申し込みます。コースBで本物のLINEとつなぐときは、ここから直接作ってかまいません（承認済みで作られます）。</p>
  <form method="post" action="{{ route('admin.stores.store') }}" class="row g-2">@csrf
    <div class="col-auto"><input name="name" class="form-control" placeholder="店舗名"></div>
    <div class="col-auto"><input name="slug" class="form-control" placeholder="slug（例 my-shop）"></div>
    <div class="col-auto"><button class="btn btn-primary">作成</button></div>
  </form>
</div></details>
@endsection
