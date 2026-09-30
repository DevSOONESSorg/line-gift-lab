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
    @php $bc = config('lab.build'); @endphp
    <form method="post" action="{{ route('mock.developers.channels.store', $provider) }}" class="ldc-create">@csrf
      <input type="hidden" name="type" value="login">
      @if ($errors->any())<div class="alert alert-danger py-2 small mb-3">@foreach ($errors->all() as $e)<div>{{ $e }}</div>@endforeach</div>@endif
      <label class="form-label">会社・事業者の所在国・地域 <span class="text-danger">*</span></label>
      <select name="country" class="form-select mb-1"><option value="">選択してください</option>@foreach (['JP' => '日本', 'TW' => '台湾', 'TH' => 'タイ', 'ID' => 'インドネシア', 'US' => 'アメリカ合衆国'] as $v => $t)<option value="{{ $v }}" @selected(old('country') === $v)>{{ $t }}</option>@endforeach</select>
      <div class="form-text mb-2">法人の場合は会社の所在国・地域を選択してください。</div>
      <label class="form-label">チャネル名 <span class="text-danger">*</span></label><input name="name" class="form-control mb-1" placeholder="例：{{ $bc['service_name'] }} {{ \App\Services\BuildGuide::current()?->store->name ?? '店舗名' }} LIFF" value="{{ old('name') }}">
      <div class="form-text mb-2">{{ $bc['channel_name_max'] }}文字以内で入力してください</div>
      <label class="form-label">チャネル説明 <span class="text-danger">*</span></label><input name="description" class="form-control mb-2" value="{{ old('description') }}">
      <p class="mb-1">アプリタイプ <span class="text-danger">*</span></p><div class="form-check mb-2"><input class="form-check-input" type="checkbox" name="app_type" value="web" id="aw" @checked(old('app_type'))><label class="form-check-label" for="aw">ウェブアプリ</label><div class="form-text">LIFF を使うときは「ウェブアプリ」を選びます</div></div>
      <div class="form-check form-switch mb-2"><input type="hidden" name="two_factor" value="0"><input class="form-check-input" type="checkbox" name="two_factor" value="1" id="tf" @checked(old('two_factor', '1') === '1')><label class="form-check-label" for="tf">2要素認証の必須化</label>
        <div class="form-text">オンにすると、ログインするお客さんにも2要素認証を求めます。</div></div>
      <label class="form-label">メールアドレス <span class="text-danger">*</span></label><input name="email" type="email" class="form-control mb-1" value="{{ old('email', $mockAccount->email ?? '') }}">
      <div class="form-text mb-2">最初は、いまログインしている人のメールアドレスが入っています。</div>
      <label class="form-label">プライバシーポリシーURL</label><input name="privacy_url" class="form-control mb-1" placeholder="https://" value="{{ old('privacy_url') }}">
      <div class="form-text mb-2">開発中のチャネルでは空欄でも作れます（本番のお店では必ず入れる）。</div>
      <label class="form-label">サービス利用規約URL</label><input name="terms_url" class="form-control mb-3" placeholder="https://" value="{{ old('terms_url') }}">
      <div class="form-check mb-3"><input class="form-check-input" type="checkbox" id="ag2" required><label class="form-check-label" for="ag2">LINE開発者契約の内容に同意します</label></div>
      <button class="btn btn-primary">作成</button>
    </form>
  </div></div>
@endif
@endsection
