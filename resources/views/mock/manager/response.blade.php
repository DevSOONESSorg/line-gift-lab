@extends('mock.manager._layout')
@section('title', '応答設定')
@section('page')
<h1 class="h4">応答設定</h1>
<form method="post" action="{{ route('mock.manager.oa.response.save', $oa) }}" class="card card-body" style="max-width:700px">@csrf
  <h2 class="h6">基本設定</h2>
  <p class="mb-1">応答モード</p>
  <div class="form-check"><input class="form-check-input" type="radio" name="response_mode" value="bot" id="rb" @checked($oa->response_mode === 'bot')><label class="form-check-label" for="rb">Bot（プログラムや自動応答が返事をする）</label></div>
  <div class="form-check mb-3"><input class="form-check-input" type="radio" name="response_mode" value="chat" id="rc" @checked($oa->response_mode === 'chat')><label class="form-check-label" for="rc">チャット（人が手で返事をする）</label></div>
  <h2 class="h6">詳細設定</h2>
  <p class="mb-1">あいさつメッセージ（友だち追加されたとき）</p>
  <div class="d-flex gap-3"><div class="form-check"><input class="form-check-input" type="radio" name="greeting_on" value="1" @checked($oa->greeting_on)><label class="form-check-label">オン</label></div><div class="form-check"><input class="form-check-input" type="radio" name="greeting_on" value="0" @checked(! $oa->greeting_on)><label class="form-check-label">オフ</label></div></div>
  <textarea name="greeting_text" class="form-control" rows="2">{{ $oa->greeting_text }}</textarea>
  <div class="form-text mb-3"><code>{Nickname}</code> は友だちの表示名、<code>{AccountName}</code> はこのアカウント名に置きかわります（本物の Manager と同じ）</div>
  <p class="mb-1">Webhook @unless ($channel)（Messaging API を有効にすると使えます）@endunless</p>
  @if ($channel)
    <div class="d-flex gap-3"><div class="form-check"><input class="form-check-input" type="radio" name="webhook" value="1" @checked($channel->use_webhook)><label class="form-check-label">オン</label></div><div class="form-check"><input class="form-check-input" type="radio" name="webhook" value="0" @checked(! $channel->use_webhook)><label class="form-check-label">オフ</label></div></div>
    <p class="small text-muted">Developers Console の「Use webhook」と同じスイッチです。</p>
  @endif
  <p class="mb-1">応答メッセージ（LINE社が自動で返す定型文）</p>
  <div class="d-flex gap-3"><div class="form-check"><input class="form-check-input" type="radio" name="auto_reply_on" value="1" @checked($oa->auto_reply_on)><label class="form-check-label">オン</label></div><div class="form-check"><input class="form-check-input" type="radio" name="auto_reply_on" value="0" @checked(! $oa->auto_reply_on)><label class="form-check-label">オフ</label></div></div>
  <textarea name="auto_reply_text" class="form-control mb-3" rows="3">{{ $oa->auto_reply_text }}</textarea>
  <button class="btn btn-success align-self-start">保存</button>
</form>
@endsection
