@extends('mock.developers._layout')
@section('title', $channel->name)
@section('page')
<h1 class="h4"><span class="badge {{ $channel->type === 'messaging' ? 'text-bg-success' : 'text-bg-primary' }}">{{ $types[$channel->type] }}</span> {{ $channel->name }}
  @if ($channel->type === 'login')<span class="badge {{ $channel->is_published ? 'text-bg-success' : 'text-bg-warning' }}">{{ $channel->is_published ? '公開済み' : '開発中' }}</span>@endif</h1>
<ul class="nav nav-tabs mb-3">
  <li class="nav-item"><a class="nav-link {{ $tab === 'basic' ? 'active' : '' }}" href="?tab=basic">Basic settings</a></li>
  @if ($channel->type === 'messaging')<li class="nav-item"><a class="nav-link {{ $tab === 'messaging' ? 'active' : '' }}" href="?tab=messaging">Messaging API</a></li>@endif
  @if ($channel->type === 'login')<li class="nav-item"><a class="nav-link {{ $tab === 'liff' ? 'active' : '' }}" href="?tab=liff">LIFF</a></li>@endif
  <li class="nav-item"><a class="nav-link {{ $tab === 'roles' ? 'active' : '' }}" href="?tab=roles">権限設定（Roles）</a></li>
</ul>

@if ($tab === 'basic')
  <div class="card card-body"><dl class="row mb-0">
    <dt class="col-3">Channel ID</dt><dd class="col-9"><code class="fs-6 user-select-all">{{ $channel->channel_id }}</code></dd>
    <dt class="col-3">Channel secret</dt><dd class="col-9"><code class="fs-6 user-select-all">{{ $channel->secret }}</code>
      <form method="post" action="{{ route('mock.developers.channel.secret', $channel) }}" class="d-inline" onsubmit="return confirm('再発行すると、今のシークレットは使えなくなります。よろしいですか？')">@csrf<button class="btn btn-sm btn-outline-secondary ms-2">再発行</button></form></dd>
    <dt class="col-3">プロバイダー</dt><dd class="col-9">{{ $provider->name }}</dd>
    @if ($channel->officialAccount)<dt class="col-3">LINE公式アカウント</dt><dd class="col-9">{{ $channel->officialAccount->name }}（{{ $channel->officialAccount->basic_id }}）</dd>@endif
    <dt class="col-3">作成者</dt><dd class="col-9">{{ $channel->created_by === 'company' ? '会社の作業アカウント' : 'あなたの個人アカウント' }}</dd>
    @if ($channel->type === 'login')
      <dt class="col-3">チャネルの状態</dt><dd class="col-9">{{ $channel->is_published ? '公開済み' : '開発中' }}
        <form method="post" action="{{ route('mock.developers.channel.publish', $channel) }}" class="d-inline">@csrf<button class="btn btn-sm btn-outline-primary ms-2">{{ $channel->is_published ? '開発中に戻す' : '公開する' }}</button></form>
        <div class="small text-muted">本物では「開発中」のあいだ、チャネルの管理者・テスター以外は LIFF を開けません。本番のお店では必ず「公開」にします。</div></dd>
    @endif
  </dl></div>
@endif

@if ($tab === 'messaging' && $channel->type === 'messaging')
  <div class="card card-body mb-3">
    <h2 class="h6">Webhook settings</h2>
    <form method="post" action="{{ route('mock.developers.channel.webhook', $channel) }}" class="d-flex gap-2">@csrf
      <input name="webhook_url" class="form-control" value="{{ $channel->webhook_url }}" placeholder="http://localhost:3000/api/webhook/line/store/{slug}"><button class="btn btn-primary">Update</button></form>
    <div class="d-flex gap-3 mt-2 align-items-center">
      <form method="post" action="{{ route('mock.developers.channel.verify', $channel) }}">@csrf<button class="btn btn-outline-secondary">Verify</button></form>
      <form method="post" action="{{ route('mock.developers.channel.use', $channel) }}">@csrf Use webhook <button class="btn btn-sm {{ $channel->use_webhook ? 'btn-success' : 'btn-secondary' }}">{{ $channel->use_webhook ? 'ON' : 'OFF' }}</button></form>
    </div>
    @if ($verify)
      @if ($verify['ok'])<div class="alert alert-success mt-2 mb-0">Success（{{ $verify['status'] }}）…自社サーバーが「受け取りました」と答えました</div>
      @else<div class="alert alert-danger mt-2 mb-0">Error（{{ $verify['status'] ?: '接続できません' }}）{{ $verify['message'] }}<br><small>URL の slug・ポート・環境が合っているか、自社サーバーが動いているかを確認しましょう。詳しくは裏側ビューへ。</small></div>@endif
    @endif
  </div>
  <div class="card card-body">
    <h2 class="h6">チャネルアクセストークン（長期）</h2>
    @if ($channel->access_token)<code class="d-block mb-2 user-select-all" style="word-break:break-all">{{ $channel->access_token }}</code>@else<p class="text-muted">まだ発行されていません。</p>@endif
    <form method="post" action="{{ route('mock.developers.channel.token', $channel) }}" @if ($channel->access_token) onsubmit="return confirm('再発行すると、今のトークンは使えなくなります。よろしいですか？')" @endif>@csrf
      <button class="btn btn-sm btn-primary">{{ $channel->access_token ? '再発行' : '発行' }}</button></form>
  </div>
@endif

@if ($tab === 'liff' && $channel->type === 'login')
  <div class="card mb-3"><table class="table mb-0 align-middle"><thead><tr><th>LIFF ID</th><th>名前</th><th>Scope</th><th>エンドポイントURL</th><th></th></tr></thead><tbody>
    @forelse ($liffs as $l)
      <tr><td><code class="user-select-all">{{ $l->liff_id }}</code></td><td>{{ $l->name }}<div class="small text-muted">{{ $l->size }}{{ $l->bot_prompt ? '・友だち追加オプションON' : '' }}</div></td><td class="small">{{ $l->scopes }}</td>
        <td><form method="post" action="{{ route('mock.developers.channel.liff.update', [$channel, $l->liff_id]) }}" class="d-flex gap-1">@csrf<input name="endpoint_url" class="form-control form-control-sm" value="{{ $l->endpoint_url }}"><button class="btn btn-sm btn-outline-primary">更新</button></form></td>
        <td><form method="post" action="{{ route('mock.developers.channel.liff.delete', [$channel, $l->liff_id]) }}" onsubmit="return confirm('削除しますか？')">@csrf @method('DELETE')<button class="btn btn-sm btn-link text-danger">削除</button></form></td></tr>
    @empty
      <tr><td colspan="5" class="text-center text-muted">LIFFアプリはまだありません</td></tr>
    @endforelse
  </tbody></table></div>
  <form method="post" action="{{ route('mock.developers.channel.liff', $channel) }}" class="card card-body" style="max-width:700px">@csrf
    <h2 class="h6">追加</h2>
    <label class="form-label">LIFFアプリ名</label><input name="name" class="form-control mb-2" value="{{ $channel->name }}">
    <p class="mb-1">サイズ</p><div class="d-flex gap-3 mb-2">@foreach (['Compact', 'Tall', 'Full'] as $s)<div class="form-check"><input class="form-check-input" type="radio" name="size" value="{{ $s }}" @checked($s === 'Full')><label class="form-check-label">{{ $s }}</label></div>@endforeach</div>
    <label class="form-label">エンドポイントURL</label><input name="endpoint_url" class="form-control mb-2" placeholder="http://localhost:3000/liff/s/{slug}">
    <p class="mb-1">Scope</p><div class="d-flex gap-3 mb-2">@foreach (['profile', 'openid', 'chat_message.write'] as $s)<div class="form-check"><input class="form-check-input" type="checkbox" name="scopes[]" value="{{ $s }}" checked><label class="form-check-label">{{ $s }}</label></div>@endforeach</div>
    <div class="form-check mb-3"><input class="form-check-input" type="checkbox" name="bot_prompt" value="1" checked><label class="form-check-label">友だち追加オプション：On（LIFFを開いたとき、お店の公式アカウントの友だち追加をすすめる）</label></div>
    <button class="btn btn-primary align-self-start">追加</button>
  </form>
@endif

@if ($tab === 'roles')
  <div class="card card-body">
    <h2 class="h6">このチャネルの権限を持つ人</h2>
    <ul>@foreach ($roles as $r)<li>{{ $r->name }}（{{ $r->email }}）— Admin</li>@endforeach</ul>
    <form method="post" action="{{ route('mock.developers.channel.roles', $channel) }}" class="d-flex gap-2">@csrf
      <select name="account_id" class="form-select w-auto">@foreach ($accounts as $a)<option value="{{ $a->id }}">{{ $a->name }}</option>@endforeach</select><button class="btn btn-primary">Admin として追加</button></form>
    <p class="small text-muted mt-2 mb-0">権限は2層です。プロバイダーのメンバー（箱に入れるか）と、チャネルごとの権限（そのチャネルを開けるか）。</p>
  </div>
@endif
@endsection
