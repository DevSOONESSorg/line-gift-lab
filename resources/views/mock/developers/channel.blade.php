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
    @php $countries = ['JP' => '日本', 'TW' => '台湾', 'TH' => 'タイ', 'ID' => 'インドネシア', 'US' => 'アメリカ合衆国']; @endphp
    @if (request('edit') === 'basic')
      <form method="post" action="{{ route('mock.developers.channel.basic', $channel) }}" class="border rounded p-3 my-2 bg-light">@csrf
        @if ($errors->any())<div class="alert alert-danger py-2 small">@foreach ($errors->all() as $e)<div>{{ $e }}</div>@endforeach</div>@endif
        <div class="f"><label>会社・事業者の所在国・地域</label><div><select name="country" class="form-select form-select-sm w-auto"><option value="">未設定</option>@foreach ($countries as $v => $t)<option value="{{ $v }}" @selected(old('country', $channel->country) === $v)>{{ $t }}</option>@endforeach</select></div></div>
        <div class="f"><label>メールアドレス</label><div><input name="email" class="form-control form-control-sm" value="{{ old('email', $channel->email) }}"></div></div>
        <div class="f"><label>プライバシーポリシーURL</label><div><input name="privacy_url" class="form-control form-control-sm" value="{{ old('privacy_url', $channel->privacy_url) }}" placeholder="https://"></div></div>
        <div class="f"><label>サービス利用規約URL</label><div><input name="terms_url" class="form-control form-control-sm" value="{{ old('terms_url', $channel->terms_url) }}" placeholder="https://"></div></div>
        @if ($channel->type === 'login')<div class="f"><label>2要素認証の必須化</label><div><div class="form-check form-switch"><input type="hidden" name="two_factor" value="0"><input class="form-check-input" type="checkbox" name="two_factor" value="1" @checked($channel->two_factor)></div></div></div>@endif
        <button class="btn btn-sm btn-primary">更新</button> <a class="btn btn-sm btn-light" href="?tab=basic">キャンセル</a>
      </form>
    @else
      <div class="f"><label>会社・事業者の所在国・地域</label><div>{{ $countries[$channel->country] ?? '未設定' }} <a class="small ms-2" href="?tab=basic&edit=basic">編集</a></div></div>
      <div class="f"><label>メールアドレス</label><div>{{ $channel->email ?: '—' }} <a class="small ms-2" href="?tab=basic&edit=basic">編集</a></div></div>
      <div class="f"><label>プライバシーポリシーURL</label><div>{{ $channel->privacy_url ?: '—' }} <a class="small ms-2" href="?tab=basic&edit=basic">編集</a></div></div>
      <div class="f"><label>サービス利用規約URL</label><div>{{ $channel->terms_url ?: '—' }} <a class="small ms-2" href="?tab=basic&edit=basic">編集</a></div></div>
      @if ($channel->type === 'login')<div class="f"><label>2要素認証の必須化</label><div>{{ $channel->two_factor ? 'オン' : 'オフ' }} <a class="small ms-2" href="?tab=basic&edit=basic">編集</a>
        <div class="form-text">オンだと、お客さんが LIFF を開くときにも2要素認証を求められます。</div></div></div>
        <div class="f"><label>アプリタイプ</label><div>ウェブアプリ</div></div>@endif
    @endif
    @if ($channel->type === 'login')
      <div class="f"><label>チャネルの状態</label><div><span class="badge {{ $channel->is_published ? 'text-bg-success' : 'text-bg-warning' }}">{{ $channel->is_published ? '公開済み' : '開発中' }}</span>
        <form method="post" action="{{ route('mock.developers.channel.publish', $channel) }}" class="d-inline">@csrf<button class="btn btn-sm btn-outline-primary ms-2">{{ $channel->is_published ? '開発中に戻す' : '公開' }}</button></form>
        <div class="form-text">「開発中」のあいだは、チャネルの管理者・テスター以外は LIFF を開けません。お店では必ず「公開」にします。</div></div></div>
    @endif
  </div></div>
  @if ($channel->type === 'login')
  <div class="card mt-3"><div class="card-body ldc-fields">
    <h2 class="h6">友だち追加オプション</h2>
    <div class="f"><label>リンクされたLINE公式アカウント</label><div>
      <form method="post" action="{{ route('mock.developers.channel.linked-oa', $channel) }}" class="d-flex gap-2">@csrf
        <select name="linked_oa_id" class="form-select form-select-sm w-auto"><option value="">–</option>@foreach ($linkable as $o)<option value="{{ $o->id }}" @selected($channel->linked_oa_id == $o->id)>{{ $o->basic_id }}/{{ $o->name }}</option>@endforeach</select>
        <button class="btn btn-sm btn-outline-primary">更新</button></form>
      <div class="form-text">LIFF の友だち追加オプション（On）で友だち追加をすすめる公式アカウント。同じプロバイダーに Messaging API チャネルがあるアカウントだけが出ます。未設定だと、友だち追加オプションが効きません。</div></div></div>
  </div></div>
  @endif
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
  @php $sizes = ['Compact' => '下半分', 'Tall' => 'ほぼ全体・トークが少し見える', 'Full' => '全画面']; $scopeList = ['openid', 'email', 'profile', 'chat_message.write']; $bp = ['normal' => 'On (normal)', 'aggressive' => 'On (aggressive)', 'off' => 'Off']; @endphp
  @if ($editLiff)
    {{-- LIFFアプリ詳細（本物と同じく、1項目ずつ直せる） --}}
    <div class="d-flex align-items-center mb-2"><h2 class="h6 mb-0">LIFFアプリ詳細</h2><a class="btn btn-sm btn-light ms-auto" href="?tab=liff">戻る</a></div>
    <form method="post" action="{{ route('mock.developers.channel.liff.update', [$channel, $editLiff->liff_id]) }}" class="card"><div class="card-body ldc-fields" style="max-width:820px">@csrf
      <input type="hidden" name="scopes_sent" value="1">
      <div class="f"><label>LIFF ID</label><div><code class="user-select-all">{{ $editLiff->liff_id }}</code> <button type="button" class="btn btn-sm btn-outline-secondary py-0" onclick="navigator.clipboard.writeText(@js($editLiff->liff_id)); this.textContent='コピーしました'">コピー</button></div></div>
      <div class="f"><label>LIFF URL</label><div><code class="user-select-all">https://liff.line.me/{{ $editLiff->liff_id }}</code> <button type="button" class="btn btn-sm btn-outline-secondary py-0" onclick="navigator.clipboard.writeText(@js('https://liff.line.me/'.$editLiff->liff_id)); this.textContent='コピーしました'">コピー</button></div></div>
      <div class="f"><label>LIFFアプリ名</label><div><input name="name" class="form-control form-control-sm" value="{{ $editLiff->name }}"></div></div>
      <div class="f"><label>サイズ</label><div class="d-flex gap-3">@foreach ($sizes as $v => $d)<div class="form-check"><input class="form-check-input" type="radio" name="size" value="{{ $v }}" @checked($editLiff->size === $v)><label class="form-check-label">{{ $v }}</label></div>@endforeach</div></div>
      <div class="f"><label>エンドポイントURL</label><div><input name="endpoint_url" class="form-control form-control-sm" value="{{ $editLiff->endpoint_url }}"></div></div>
      <div class="f"><label>Scope</label><div><div class="d-flex gap-3 flex-wrap">@foreach ($scopeList as $sc)<div class="form-check"><input class="form-check-input" type="checkbox" name="scopes[]" value="{{ $sc }}" @checked(in_array($sc, explode(' ', $editLiff->scopes), true)) @disabled($sc === 'email')><label class="form-check-label">{{ $sc }}</label></div>@endforeach</div>
        <div class="form-text">「chat_message.write」スコープを有効にすると、ブラウザの最小化機能が無効になります</div></div></div>
      <div class="f"><label>友だち追加オプション</label><div class="d-flex gap-3">@foreach ($bp as $v => $t)<div class="form-check"><input class="form-check-input" type="radio" name="bot_prompt" value="{{ $v }}" @checked(($editLiff->bot_prompt ? 'normal' : 'off') === $v)><label class="form-check-label">{{ $t }}</label></div>@endforeach</div></div>
      <div class="d-flex gap-2 mt-2"><button class="btn btn-primary">更新</button></div>
    </div></form>
    <form method="post" action="{{ route('mock.developers.channel.liff.delete', [$channel, $editLiff->liff_id]) }}" class="mt-2" onsubmit="return confirm('削除しますか？')">@csrf @method('DELETE')<button class="btn btn-sm btn-link text-danger">削除</button></form>
  @else
  <div class="d-flex align-items-center mb-2"><h2 class="h6 mb-0">LIFFアプリ</h2><a class="btn btn-sm btn-primary ms-auto" href="?tab=liff&add=1">追加</a></div>
  <div class="card mb-3"><table class="table mb-0 align-middle"><thead><tr><th>LIFFアプリ名</th><th>LIFF ID</th><th>LIFF URL</th><th>サイズ</th></tr></thead><tbody>
    @forelse ($liffs as $l)
      <tr><td><a href="?tab=liff&liff={{ $l->liff_id }}">{{ $l->name }}</a></td>
        <td><code class="user-select-all">{{ $l->liff_id }}</code></td>
        <td><code class="user-select-all small">https://liff.line.me/{{ $l->liff_id }}</code></td>
        <td>{{ $l->size }}</td></tr>
    @empty
      <tr><td colspan="4" class="text-center text-muted py-3">LIFFアプリはまだありません。「追加」から作りましょう。</td></tr>
    @endforelse
  </tbody></table></div>
  <p class="small text-muted">LIFFアプリ名を押すと「LIFFアプリ詳細」が開き、エンドポイントURL・Scope・友だち追加オプションの確認や変更、LIFF ID のコピーができます。</p>
  @endif
  @if (request('add'))
  <form method="post" action="{{ route('mock.developers.channel.liff', $channel) }}" class="card"><div class="card-body ldc-fields" style="max-width:820px">@csrf
    <h2 class="h6">LIFFアプリを追加</h2>
    <div class="f"><label>LIFFアプリ名 <span class="text-danger">*</span></label><div><input name="name" class="form-control" placeholder="例：{{ (\App\Services\BuildGuide::current()?->targetSlug() ?? 'slug').config('lab.build.liff_suffix') }}"></div></div>
    <div class="f"><label>サイズ <span class="text-danger">*</span></label><div class="d-flex gap-3">@foreach ($sizes as $s => $d)<div class="form-check"><input class="form-check-input" type="radio" name="size" value="{{ $s }}"><label class="form-check-label">{{ $s }} <small class="text-muted">{{ $d }}</small></label></div>@endforeach</div></div>
    <div class="f"><label>エンドポイントURL <span class="text-danger">*</span></label><div><input name="endpoint_url" class="form-control" placeholder="https://"><div class="form-text">LIFF を開いたときに表示するページ。本番は https:// のみ。</div></div></div>
    <div class="f"><label>Scope</label><div><div class="d-flex gap-3 flex-wrap">@foreach (['openid' => true, 'email' => false, 'profile' => false, 'chat_message.write' => false] as $s => $on)<div class="form-check"><input class="form-check-input" type="checkbox" name="scopes[]" value="{{ $s }}" @checked($on) @disabled($s === 'email')><label class="form-check-label">{{ $s }}</label></div>@endforeach</div>
      <div class="form-text">「chat_message.write」スコープを有効にすると、ブラウザの最小化機能が無効になります</div></div></div>
    <div class="f"><label>友だち追加オプション</label><div class="d-flex gap-3">@foreach ($bp as $v => $t)<div class="form-check"><input class="form-check-input" type="radio" name="bot_prompt" value="{{ $v }}" @checked($v === 'off')><label class="form-check-label">{{ $t }}</label></div>@endforeach</div>
      <div class="form-text ms-0">LIFF を開いたとき、このチャネルの「リンクされたLINE公式アカウント」の友だち追加をすすめる</div></div>
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
