@extends('mock.manager._layout')
@section('title', 'Messaging API')
@section('page')
<h1 class="h4">Messaging API</h1>
@if ($channel)
  <div class="card mb-3"><div class="card-body">
    <dl class="row mb-0">
      <dt class="col-sm-3">ステータス</dt><dd class="col-sm-9"><span class="badge text-bg-success">利用中</span></dd>
      <dt class="col-sm-3">プロバイダー</dt><dd class="col-sm-9">{{ $provider->name }}</dd>
      <dt class="col-sm-3">Channel ID</dt><dd class="col-sm-9"><code class="user-select-all">{{ $channel->channel_id }}</code></dd>
      <dt class="col-sm-3">Channel secret</dt><dd class="col-sm-9"><code class="user-select-all">{{ $channel->secret }}</code></dd>
      <dt class="col-sm-3">Webhook URL</dt><dd class="col-sm-9">{{ $channel->webhook_url ?: '未設定' }} <span class="small text-muted">（登録・検証は LINE Developers で行います）</span></dd>
      <dt class="col-sm-3">プライバシーポリシー</dt><dd class="col-sm-9">{{ $channel->privacy_url ?: '未設定' }}</dd>
      <dt class="col-sm-3">利用規約</dt><dd class="col-sm-9">{{ $channel->terms_url ?: '未設定' }}</dd>
      <dt class="col-sm-3">チャネルの権限</dt><dd class="col-sm-9">{{ $hasRole ? 'あり' : '権限なし（LINE Developers ではグレーになります）' }}</dd>
    </dl>
  </div></div>
  <form method="post" action="{{ route('mock.manager.oa.messaging.settings', $oa) }}" class="card card-body mb-3" style="max-width:760px">@csrf
    <h2 class="h6">プライバシーポリシーと利用規約</h2>
    @if ($errors->any())<div class="alert alert-danger py-2 small">@foreach ($errors->all() as $e)<div>{{ $e }}</div>@endforeach</div>@endif
    <input name="privacy_url" class="form-control mb-2" placeholder="プライバシーポリシー https://..." value="{{ old('privacy_url', $channel->privacy_url) }}">
    <input name="terms_url" class="form-control mb-2" placeholder="利用規約 https://..." value="{{ old('terms_url', $channel->terms_url) }}">
    <button class="btn btn-outline-secondary btn-sm align-self-start">保存</button>
  </form>
  @if ($hasRole)<a class="btn btn-primary" href="{{ route('mock.developers.channel.show', [$channel, 'tab' => 'messaging']) }}">LINE Developers で開く <i class="bi bi-box-arrow-up-right"></i></a>@endif
@else
  <div class="card card-body mb-3" style="max-width:760px">
    <p class="mb-2">Messaging API を利用すると、自社のシステム（bot）とこの公式アカウントをつなげられます。</p>
    <p class="small text-muted mb-0">「Messaging APIを利用する」を押すと、<strong>Messaging API チャネルが自動で作られます</strong>（LINE Developers から直接は作れません）。</p>
  </div>
  <form method="post" action="{{ route('mock.manager.oa.messaging.enable', $oa) }}" class="card card-body" style="max-width:760px"
        onsubmit="return confirm('Messaging APIを有効にしますか？\n\n選んだプロバイダーは、あとから変更できません。')">@csrf
    <h2 class="h6">① 開発者情報</h2>
    <div class="row g-2 mb-3"><div class="col"><label class="form-label small">名前</label><input class="form-control" value="{{ $mockAccount->name }}"></div>
      <div class="col"><label class="form-label small">メールアドレス</label><input class="form-control" value="{{ $mockAccount->email }}"></div></div>
    <h2 class="h6">② プロバイダーを選択</h2>
    <div class="alert alert-warning py-2 small">プロバイダーは <strong>あとから変更できません</strong>。お店のオンボーディングでは、必ず運営のプロバイダーを選びます。</div>
    @forelse ($providers as $p)<div class="form-check"><input class="form-check-input" type="radio" name="provider_id" value="{{ $p->id }}" id="p{{ $p->id }}"><label class="form-check-label" for="p{{ $p->id }}">{{ $p->name }}</label></div>
    @empty<p class="small text-muted">「{{ $mockAccount->name }}」がメンバーのプロバイダーはありません。</p>@endforelse
    <div class="form-check d-flex gap-2 align-items-center"><input class="form-check-input" type="radio" name="provider_id" value="new" id="pn"><label class="form-check-label text-nowrap" for="pn">プロバイダーを作成：</label><input name="new_provider" class="form-control form-control-sm" placeholder="プロバイダー名"></div>
    <p class="small text-muted mt-1">選べるのは、いまログイン中の「{{ $mockAccount->name }}」がメンバーになっているプロバイダーだけです。チャネルの権限は、有効にした人に付きます。</p>
    <h2 class="h6 mt-2">③ プライバシーポリシーと利用規約（任意）</h2>
    <input name="privacy_url" class="form-control mb-2" placeholder="プライバシーポリシー https://..."><input name="terms_url" class="form-control mb-3" placeholder="利用規約 https://...">
    <button class="btn btn-line align-self-start px-4">Messaging APIを利用する</button>
  </form>
@endif
@endsection
