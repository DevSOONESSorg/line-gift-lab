@extends('mock.developers._layout')
@section('title', $channel->name)
@section('page')
<div class="d-flex align-items-center gap-3 mb-3">
  <span class="ch-icon ch-{{ $channel->type }}"><i class="bi {{ $channel->type === 'messaging' ? 'bi-chat-dots' : 'bi-box-arrow-in-right' }}"></i></span>
  <div><h1 class="h4 mb-0">{{ $channel->name }}</h1>
    <div class="small text-muted">{{ $types[$channel->type] }} ・ チャネルID {{ $channel->channel_id }}
      @if ($channel->type === 'login') ・ <span class="badge {{ $channel->is_published ? 'text-bg-success' : 'text-bg-warning' }}">{{ $channel->is_published ? '公開済み' : '開発中' }}</span>@endif</div></div>
</div>
<ul class="nav nav-tabs mb-3">
  <li class="nav-item"><a class="nav-link {{ $tab === 'basic' ? 'active' : '' }}" href="?tab=basic">チャネル基本設定</a></li>
  @if ($channel->type === 'messaging')<li class="nav-item"><a class="nav-link {{ $tab === 'messaging' ? 'active' : '' }}" href="?tab=messaging">Messaging API設定</a></li>@endif
  @if ($channel->type === 'login')<li class="nav-item"><span class="nav-link disabled">LINEログイン設定</span></li><li class="nav-item"><a class="nav-link {{ $tab === 'liff' ? 'active' : '' }}" href="?tab=liff">LIFF</a></li>@endif
  <li class="nav-item"><a class="nav-link {{ $tab === 'roles' ? 'active' : '' }}" href="?tab=roles">権限設定</a></li>
</ul>

@if ($tab === 'basic')
  <div class="card"><div class="card-body ldc-fields">
    <div class="f"><label>チャネルID</label><div><code class="fs-6 user-select-all">{{ $channel->channel_id }}</code></div></div>
    <div class="f"><label>チャネルシークレット</label><div><code class="fs-6 user-select-all">{{ $channel->secret }}</code>
      <form method="post" action="{{ route('mock.developers.channel.secret', $channel) }}" class="d-inline" onsubmit="return confirm('再発行すると、今のシークレットは使えなくなります。よろしいですか？')">@csrf<button class="btn btn-sm btn-outline-secondary ms-2">再発行</button></form>
      <div class="form-text">{{ $channel->type === 'messaging' ? '署名（X-Line-Signature）の計算に使う合言葉。人に見せない。' : 'LINEログインの認証で使う秘密の値。人に見せない。' }}</div></div></div>
    <div class="f"><label>チャネル名</label><div>{{ $channel->name }}</div></div>
    <div class="f"><label>チャネル説明</label><div>{{ $channel->description ?: '—' }}</div></div>
    <div class="f"><label>プロバイダー</label><div>{{ $provider->name }}</div></div>
    @if ($channel->officialAccount)<div class="f"><label>LINE公式アカウント</label><div>{{ $channel->officialAccount->name }}（{{ $channel->officialAccount->basic_id }}）</div></div>@endif
    <div class="f"><label>作成者</label><div>{{ \App\Services\BuildGuide::ACCOUNTS[$channel->created_by] ?? $channel->created_by }}</div></div>
    @if ($channel->type === 'login')
      <div class="f"><label>チャネルの状態</label><div><span class="badge {{ $channel->is_published ? 'text-bg-success' : 'text-bg-warning' }}">{{ $channel->is_published ? '公開済み' : '開発中' }}</span>
        <form method="post" action="{{ route('mock.developers.channel.publish', $channel) }}" class="d-inline">@csrf<button class="btn btn-sm btn-outline-primary ms-2">{{ $channel->is_published ? '開発中に戻す' : '公開' }}</button></form>
        <div class="form-text">「開発中」のあいだは、チャネルの管理者・テスター以外は LIFF を開けません。お店では必ず「公開」にします。</div></div></div>
    @endif
  </div></div>
@endif

@if ($tab === 'messaging' && $channel->type === 'messaging')
  @php $oa = $channel->officialAccount; @endphp
  <div class="card mb-3"><div class="card-body ldc-fields">
    <h2 class="h6">ボット情報</h2>
    <div class="f"><label>ボットのベーシックID</label><div><code>{{ $oa?->basic_id }}</code></div></div>
    <div class="f"><label>QRコード</label><div><a class="small" href="{{ route('mock.phone', ['add' => $oa?->basic_id]) }}">お客さんのスマホで読み取る</a></div></div>
  </div></div>

  <div class="card mb-3"><div class="card-body ldc-fields">
    <h2 class="h6">Webhook設定</h2>
    <div class="f"><label>Webhook URL</label><div>
      @if (request('edit') === 'webhook')
        <form method="post" action="{{ route('mock.developers.channel.webhook', $channel) }}" class="d-flex gap-2">@csrf
          <input name="webhook_url" class="form-control" value="{{ $channel->webhook_url }}" placeholder="{{ \App\Services\BuildGuide::current()?->webhookUrl() ?? 'http://localhost:3000/api/webhook/line/store/{slug}' }}" autofocus>
          <button class="btn btn-primary text-nowrap">更新</button><a class="btn btn-light text-nowrap" href="?tab=messaging">キャンセル</a></form>
      @else
        <span class="me-2">{{ $channel->webhook_url ?: '未設定' }}</span>
        <a class="btn btn-sm btn-outline-secondary" href="?tab=messaging&edit=webhook">編集</a>
        @if ($channel->webhook_url)<form method="post" action="{{ route('mock.developers.channel.verify', $channel) }}" class="d-inline">@csrf<button class="btn btn-sm btn-outline-primary">検証</button></form>@endif
        @if ($channel->webhook_verified_at)<span class="small text-success ms-2"><i class="bi bi-check-circle"></i> 検証済み</span>@endif
      @endif
      @if ($verify)
        @if ($verify['ok'])<div class="alert alert-success mt-2 mb-0 py-2"><b>成功</b>（{{ $verify['status'] }}）… 自社サーバーが「受け取りました」と答えました</div>
        @else<div class="alert alert-danger mt-2 mb-0 py-2"><b>エラー</b>（{{ $verify['status'] ?: '接続できません' }}）{{ $verify['message'] }}<br><small>URL の slug・ポート・環境が合っているか、自社サーバーが動いているか、管理画面のシークレットが合っているかを確認しましょう。詳しくは裏側ビューへ。</small></div>@endif
      @endif
    </div></div>
    <div class="f"><label>Webhookの利用</label><div>
      <form method="post" action="{{ route('mock.developers.channel.use', $channel) }}">@csrf
        <div class="form-check form-switch"><input class="form-check-input" type="checkbox" role="switch" @checked($channel->use_webhook) onchange="this.form.submit()"><label class="form-check-label">{{ $channel->use_webhook ? 'オン' : 'オフ' }}</label></div></form>
      <div class="form-text">Manager の応答設定の「Webhook」と同じスイッチです。</div></div></div>
  </div></div>

  <div class="card mb-3"><div class="card-body ldc-fields">
    <h2 class="h6">LINE公式アカウント機能</h2>
    <div class="f"><label>応答メッセージ</label><div>{{ $oa?->auto_reply_on ? '有効' : '無効' }} @if ($oa)<a class="small ms-2" href="{{ route('mock.manager.oa.response', $oa) }}">編集 <i class="bi bi-box-arrow-up-right"></i></a>@endif</div></div>
    <div class="f"><label>あいさつメッセージ</label><div>{{ $oa?->greeting_on ? '有効' : '無効' }} @if ($oa)<a class="small ms-2" href="{{ route('mock.manager.oa.response', $oa) }}">編集 <i class="bi bi-box-arrow-up-right"></i></a>@endif</div></div>
  </div></div>

  <div class="card"><div class="card-body ldc-fields">
    <h2 class="h6">チャネルアクセストークン</h2>
    <div class="f"><label>チャネルアクセストークン（長期）</label><div>
      @if ($channel->access_token)<code class="d-block mb-2 user-select-all" style="word-break:break-all">{{ $channel->access_token }}</code>@else<p class="text-muted mb-2">まだ発行されていません。</p>@endif
      <form method="post" action="{{ route('mock.developers.channel.token', $channel) }}" @if ($channel->access_token) onsubmit="return confirm('再発行すると、今のトークンは使えなくなります。よろしいですか？')" @endif>@csrf
        <button class="btn btn-sm btn-primary">{{ $channel->access_token ? '再発行' : '発行' }}</button></form>
      <div class="form-text">自社サーバーが LINE に送信をお願いするときの許可証。人に見せない・チャットに貼らない。</div></div></div>
  </div></div>
@endif

@if ($tab === 'liff' && $channel->type === 'login')
  <div class="d-flex align-items-center mb-2"><h2 class="h6 mb-0">LIFFアプリ</h2><a class="btn btn-sm btn-primary ms-auto" href="?tab=liff&add=1">追加</a></div>
  <div class="card mb-3"><table class="table mb-0 align-middle"><thead><tr><th>LIFFアプリ名</th><th>LIFF ID / LIFF URL</th><th>エンドポイントURL</th><th></th></tr></thead><tbody>
    @forelse ($liffs as $l)
      <tr><td>{{ $l->name }}<div class="small text-muted">{{ $l->size }}・{{ $l->scopes }}{{ $l->bot_prompt ? '・友だち追加オプション On' : '' }}</div></td>
        <td><code class="user-select-all">{{ $l->liff_id }}</code><div class="small"><code class="user-select-all">https://liff.line.me/{{ $l->liff_id }}</code></div></td>
        <td><form method="post" action="{{ route('mock.developers.channel.liff.update', [$channel, $l->liff_id]) }}" class="d-flex gap-1">@csrf<input name="endpoint_url" class="form-control form-control-sm" value="{{ $l->endpoint_url }}"><button class="btn btn-sm btn-outline-primary">更新</button></form></td>
        <td><form method="post" action="{{ route('mock.developers.channel.liff.delete', [$channel, $l->liff_id]) }}" onsubmit="return confirm('削除しますか？')">@csrf @method('DELETE')<button class="btn btn-sm btn-link text-danger">削除</button></form></td></tr>
    @empty
      <tr><td colspan="4" class="text-center text-muted py-3">LIFFアプリはまだありません。「追加」から作りましょう。</td></tr>
    @endforelse
  </tbody></table></div>
  @if (request('add'))
  <form method="post" action="{{ route('mock.developers.channel.liff', $channel) }}" class="card"><div class="card-body ldc-fields" style="max-width:820px">@csrf
    <h2 class="h6">LIFFアプリを追加</h2>
    <div class="f"><label>LIFFアプリ名 <span class="text-danger">*</span></label><div><input name="name" class="form-control" value="{{ $channel->name }}"></div></div>
    <div class="f"><label>サイズ <span class="text-danger">*</span></label><div class="d-flex gap-3">@foreach (['Compact' => '下半分', 'Tall' => 'ほぼ全体・トークが少し見える', 'Full' => '全画面'] as $s => $d)<div class="form-check"><input class="form-check-input" type="radio" name="size" value="{{ $s }}" @checked($s === 'Tall')><label class="form-check-label">{{ $s }} <small class="text-muted">{{ $d }}</small></label></div>@endforeach</div></div>
    <div class="f"><label>エンドポイントURL <span class="text-danger">*</span></label><div><input name="endpoint_url" class="form-control" placeholder="{{ \App\Services\BuildGuide::current()?->liffUrl() ?? 'http://localhost:3000/liff/s/{slug}' }}"><div class="form-text">LIFF を開いたときに表示するページ。本番は https:// のみ。</div></div></div>
    <div class="f"><label>Scope</label><div class="d-flex gap-3 flex-wrap">@foreach (['openid' => true, 'email' => false, 'profile' => true, 'chat_message.write' => true] as $s => $on)<div class="form-check"><input class="form-check-input" type="checkbox" name="scopes[]" value="{{ $s }}" @checked($on) @disabled($s === 'email')><label class="form-check-label">{{ $s }}</label></div>@endforeach</div></div>
    <div class="f"><label>友だち追加オプション</label><div class="d-flex gap-3">@foreach (['normal' => 'On (normal)', 'aggressive' => 'On (aggressive)', 'off' => 'Off'] as $v => $t)<div class="form-check"><input class="form-check-input" type="radio" name="bot_prompt" value="{{ $v }}" @checked($v === 'normal')><label class="form-check-label">{{ $t }}</label></div>@endforeach</div>
      <div class="form-text ms-0">LIFF を開いたとき、この LINEログインチャネルとつながった公式アカウントの友だち追加をすすめる</div></div>
    <div class="f"><label>Scan QR</label><div><div class="form-check form-switch"><input class="form-check-input" type="checkbox" disabled></div></div></div>
    <div class="f"><label>モジュールモード</label><div><div class="form-check form-switch"><input class="form-check-input" type="checkbox" disabled></div><div class="form-text">Full のときだけ使えます</div></div></div>
    <button class="btn btn-primary mt-2">追加</button>
  </div></form>
  @endif
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
