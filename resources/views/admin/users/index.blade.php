@extends('layouts.admin')
@section('title', 'ユーザー管理')
@section('content')
<h1 class="h3 mb-3">ユーザー管理</h1>
<p class="text-muted small">ギフトを贈ったお客さん（LINEユーザー）の一覧です。管理画面にログインする運営スタッフとは別のものです。</p>
<form class="row g-2 mb-3"><div class="col-auto"><input name="q" class="form-control" placeholder="名前・LINE表示名・メール" value="{{ request('q') }}"></div><div class="col-auto"><button class="btn btn-secondary">検索</button></div></form>
<div class="card"><table class="table table-hover mb-0 align-middle">
  <thead><tr><th>ID</th><th>名前</th><th>LINE表示名</th><th>メール</th><th>注文数</th><th>カード</th><th>身分証</th><th>登録日</th><th></th></tr></thead>
  <tbody>
  @forelse ($customers as $c)
    <tr><td>{{ $c->id }}</td><td>{{ $c->name ?: '—' }}</td><td>{{ $c->line_display_name }}</td><td>{{ $c->email ?: '—' }}</td><td>{{ $c->orders_count }}</td>
      <td>{{ $c->card_last4 ? "**** {$c->card_last4}" : '—' }}</td><td>{{ $c->id_verified_at ? '登録済み' : '—' }}</td>
      <td class="small">{{ $c->created_at->format('Y/m/d') }}</td><td><a class="btn btn-sm btn-outline-primary" href="{{ route('admin.users.show', $c) }}">詳細</a></td></tr>
  @empty
    <tr><td colspan="9" class="text-center text-muted py-4">まだいません</td></tr>
  @endforelse
  </tbody></table></div>
<div class="mt-3">{{ $customers->links('pagination::bootstrap-5') }}</div>
@endsection
