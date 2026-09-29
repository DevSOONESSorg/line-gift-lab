@extends('layouts.admin')
@section('title', 'ユーザー詳細')
@section('content')
<p><a href="{{ route('admin.users.index') }}">← ユーザー管理</a></p>
<h1 class="h3 mb-3">{{ $customer->name ?: $customer->line_display_name }}</h1>
<div class="card mb-3"><div class="card-header">基本情報</div><div class="card-body"><dl class="row mb-0">
  <dt class="col-3">ID</dt><dd class="col-9">{{ $customer->id }}</dd>
  <dt class="col-3">名前</dt><dd class="col-9">{{ $customer->name ?: '—' }}</dd>
  <dt class="col-3">LINE表示名</dt><dd class="col-9">{{ $customer->line_display_name }}</dd>
  <dt class="col-3">LINE ID</dt><dd class="col-9"><code>{{ $customer->line_user_id }}</code></dd>
  <dt class="col-3">登録カード</dt><dd class="col-9">{{ $customer->card_last4 ? "**** {$customer->card_last4}（{$customer->card_exp}）" : '—' }}</dd>
  <dt class="col-3">身分証</dt><dd class="col-9">{{ $customer->id_verified_at?->format('Y/m/d H:i') ?? '未登録' }}</dd>
  <dt class="col-3">登録日</dt><dd class="col-9">{{ $customer->created_at->format('Y/m/d H:i') }}</dd>
</dl></div></div>
<div class="card"><div class="card-header">注文履歴</div><table class="table mb-0"><thead><tr><th>ID</th><th>店舗</th><th>メニュー</th><th>金額</th><th>ステータス</th><th>日時</th></tr></thead><tbody>
  @forelse ($orders as $o)
    <tr><td><a href="{{ route('admin.orders.show', $o) }}">{{ $o->id }}</a></td><td>{{ $o->store->name }}</td><td>{{ $o->menu_name }}</td><td>¥{{ number_format($o->amount) }}</td>
      <td><span class="badge text-bg-{{ $o->status->color() }}">{{ $o->status->label() }}</span></td><td class="small">{{ $o->created_at->format('Y/m/d H:i') }}</td></tr>
  @empty
    <tr><td colspan="6" class="text-muted text-center">注文はありません</td></tr>
  @endforelse
</tbody></table></div>
@endsection
