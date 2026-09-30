@extends('mock.manager._layout')
@section('title', '応答設定')
@section('page')
<h1 class="h4">応答設定</h1>
<form method="post" action="{{ route('mock.manager.oa.response.save', $oa) }}" class="card card-body oam-form" style="max-width:760px">@csrf
  <h2 class="h6">基本設定</h2>
  <div class="oam-row"><div><b>チャット</b><div class="small text-muted">オンにすると、人が手で返事をするモード。Webhook（bot）は動かなくなります（この教材の仕様）。</div></div>
    <div class="form-check form-switch"><input type="hidden" name="response_mode" value="bot"><input class="form-check-input" type="checkbox" name="response_mode" value="chat" @checked($oa->response_mode === 'chat')></div></div>
  <div class="oam-row"><div><b>あいさつメッセージ</b><div class="small text-muted">友だち追加されたときに送るメッセージ。<code>{Nickname}</code> は友だちの表示名、<code>{AccountName}</code> はアカウント名に置きかわります。</div></div>
    <div class="form-check form-switch"><input type="hidden" name="greeting_on" value="0"><input class="form-check-input" type="checkbox" name="greeting_on" value="1" @checked($oa->greeting_on)></div></div>
  <textarea name="greeting_text" class="form-control mb-3" rows="2">{{ $oa->greeting_text }}</textarea>

  <h2 class="h6 mt-2">詳細設定</h2>
  <div class="oam-row"><div><b>応答メッセージ</b><div class="small text-muted">LINE社が自動で返す定型文。bot と一緒に使うと、返事が2通になります。</div></div>
    <div class="form-check form-switch"><input type="hidden" name="auto_reply_on" value="0"><input class="form-check-input" type="checkbox" name="auto_reply_on" value="1" @checked($oa->auto_reply_on)></div></div>
  <textarea name="auto_reply_text" class="form-control mb-3" rows="2">{{ $oa->auto_reply_text }}</textarea>
  <div class="oam-row"><div><b>Webhook</b><div class="small text-muted">@if ($channel)トークのできごとを自社サーバーに知らせる。LINE Developers の「Webhookの利用」と同じスイッチです。@else Messaging API を利用すると設定できます。@endif</div></div>
    @if ($channel)<div class="form-check form-switch"><input type="hidden" name="webhook" value="0"><input class="form-check-input" type="checkbox" name="webhook" value="1" @checked($channel->use_webhook)></div>@else<span class="text-muted">—</span>@endif</div>
  <button class="btn btn-line align-self-start px-4 mt-3">保存</button>
</form>
@endsection
