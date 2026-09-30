@extends('mock.developers._layout')
@section('title', $provider->name)
@section('page')
<h1 class="h4">{{ $provider->name }}</h1>
<ul class="nav nav-tabs mb-3"><li class="nav-item"><span class="nav-link active">チャネル設定</span></li><li class="nav-item"><span class="nav-link disabled">権限設定</span></li></ul>
<div class="d-flex flex-wrap gap-3">
  <a class="ch-card ch-new" href="{{ route('mock.developers.provider', [$provider, 'create' => 'login']) }}"><i class="bi bi-plus-lg fs-3"></i><strong>新規チャネル作成</strong></a>
  @foreach ($channels as $c)
    @if ($c->hasRole)
      <a class="ch-card" href="{{ route('mock.developers.channel.show', $c) }}"><span class="ch-type ch-{{ $c->type }}">{{ $types[$c->type] }}</span><strong>{{ $c->name }}</strong><small>{{ $c->channel_id }}</small></a>
    @else
      <div class="ch-card disabled" title="このチャネルの権限がありません"><span class="ch-type">{{ $types[$c->type] }}</span><strong>{{ $c->name }}</strong><small>権限なし</small></div>
    @endif
  @endforeach
</div>
<p class="small text-muted mt-2">グレーのチャネルは「権限なし」です。自分が作っていないチャネルはこうなります（不具合ではありません）。</p>

@if ($creating)
  <div class="card mt-4" style="max-width:720px"><div class="card-body">
    <h2 class="h5">新規チャネル作成</h2>
    <p class="mb-2">チャネルの種類を選んでください</p>
    <div class="d-flex gap-2 mb-3">
      <div class="ch-kind on"><i class="bi bi-box-arrow-in-right"></i> LINEログイン</div>
      <div class="ch-kind off" title="Messaging API チャネルは LINE Official Account Manager で作ります"><i class="bi bi-chat-dots"></i> Messaging API<br><small>Manager から作成</small></div>
      <div class="ch-kind off"><i class="bi bi-phone"></i> LINEミニアプリ</div>
    </div>
    <div class="alert alert-info py-2 small">Messaging API チャネルは、ここからは作れません。LINE Official Account Manager の「Messaging APIを利用する」で自動で作られます（本物と同じ）。</div>
    <form method="post" action="{{ route('mock.developers.channels.store', $provider) }}">@csrf
      <input type="hidden" name="type" value="login">
      <label class="form-label">チャネル名</label><input name="name" class="form-control mb-2" placeholder="例：{{ \App\Services\BuildGuide::current()?->store->name ?? '店舗名' }} LIFF" value="{{ old('name') }}">
      <label class="form-label">チャネル説明</label><input name="description" class="form-control mb-2">
      <p class="mb-1">アプリタイプ</p><div class="form-check mb-3"><input class="form-check-input" type="checkbox" name="app_type" value="web" id="aw"><label class="form-check-label" for="aw">ウェブアプリ</label><div class="form-text">LIFF を使うときは「ウェブアプリ」を選びます</div></div>
      <div class="form-check mb-3"><input class="form-check-input" type="checkbox" id="ag2" required><label class="form-check-label" for="ag2">LINE開発者契約の内容に同意します</label></div>
      <button class="btn btn-primary">作成</button>
    </form>
  </div></div>
@endif
@endsection
