@extends('mock.developers._layout')
@section('title', $provider->name)
@section('page')
<h1 class="h4">{{ $provider->name }}</h1>
<h2 class="h6 mt-3">チャネル <a class="btn btn-sm btn-primary" href="{{ route('mock.developers.provider', [$provider, 'create' => 'login']) }}">新規チャネル作成</a></h2>
@if ($creating)
  <form method="post" action="{{ route('mock.developers.channels.store', $provider) }}" class="card card-body mb-3" style="max-width:600px">@csrf
    <p class="mb-1">チャネルの種類</p>
    <div class="form-check"><input class="form-check-input" type="radio" name="type" value="login" checked><label class="form-check-label">LINEログイン</label></div>
    <div class="form-check mb-2"><input class="form-check-input" type="radio" name="type" value="messaging"><label class="form-check-label">Messaging API</label></div>
    <label class="form-label">チャネル名</label><input name="name" class="form-control mb-2" placeholder="店舗名 LIFF">
    <label class="form-label">チャネル説明</label><input name="description" class="form-control mb-2">
    <p class="mb-1">アプリタイプ</p><div class="form-check mb-3"><input class="form-check-input" type="checkbox" name="app_type" value="web"><label class="form-check-label">ウェブアプリ</label></div>
    <button class="btn btn-primary align-self-start">作成</button>
  </form>
@endif
<div class="d-flex flex-wrap gap-3">
  @foreach ($channels as $c)
    @if ($c->hasRole)
      <a class="ch-card" href="{{ route('mock.developers.channel.show', $c) }}"><span class="badge {{ $c->type === 'messaging' ? 'text-bg-success' : 'text-bg-primary' }}">{{ $types[$c->type] }}</span><strong>{{ $c->name }}</strong><small>{{ $c->channel_id }}</small></a>
    @else
      <div class="ch-card disabled" title="このチャネルの権限がありません"><span class="badge text-bg-secondary">{{ $types[$c->type] }}</span><strong>{{ $c->name }}</strong><small>権限なし</small></div>
    @endif
  @endforeach
</div>
<p class="small text-muted mt-2">グレーのチャネルは「権限なし」です。自分が作っていないチャネルはこうなります（不具合ではありません）。</p>
@endsection
