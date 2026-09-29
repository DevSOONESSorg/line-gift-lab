@extends('mock.manager._layout')
@section('title', 'Messaging API')
@section('page')
<h1 class="h4">Messaging API</h1>
@if ($channel)
  <div class="card card-body">
    <div class="alert alert-success">Messaging API は有効です。</div>
    <dl class="row mb-0">
      <dt class="col-3">プロバイダー</dt><dd class="col-9">{{ $provider->name }}</dd>
      <dt class="col-3">Channel ID</dt><dd class="col-9"><code>{{ $channel->channel_id }}</code></dd>
      <dt class="col-3">チャネルの権限</dt><dd class="col-9">{{ $hasRole ? 'あり（Developers Console で開けます）' : '権限なし（Developers Console ではグレーになります）' }}</dd>
    </dl>
    @if ($hasRole)<a class="btn btn-primary mt-2 align-self-start" href="{{ route('mock.developers.channel.show', $channel) }}">LINE Developers で開く →</a>@endif
  </div>
@else
  <form method="post" action="{{ route('mock.manager.oa.messaging.enable', $oa) }}" class="card card-body" style="max-width:700px">@csrf
    <p>Messaging API を使うと、自社サーバー（bot）とこの公式アカウントをつなげられます。</p>
    <p class="fw-bold mb-1">プロバイダーを選択（チャネルを入れる「会社の箱」。あとから変えられません）</p>
    @foreach ($providers as $p)<div class="form-check"><input class="form-check-input" type="radio" name="provider_id" value="{{ $p->id }}" id="p{{ $p->id }}"><label class="form-check-label" for="p{{ $p->id }}">{{ $p->name }}</label></div>@endforeach
    <div class="form-check d-flex gap-2 align-items-center"><input class="form-check-input" type="radio" name="provider_id" value="new" id="pn"><label class="form-check-label text-nowrap" for="pn">新しいプロバイダーを作成：</label><input name="new_provider" class="form-control form-control-sm" placeholder="プロバイダー名"></div>
    <button class="btn btn-success mt-3 align-self-start">Messaging APIを利用する</button>
    <p class="small text-muted mt-2 mb-0">いまログイン中の「{{ $mockAccount->name }}」がメンバーになっているプロバイダーだけが選べます。チャネルの権限は、有効化した人に付きます。</p>
  </form>
@endif
@endsection
