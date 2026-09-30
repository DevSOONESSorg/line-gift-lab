@extends('mock.manager._layout')
@section('title', '応答設定')
@section('page')
<h1 class="h4">応答設定</h1>
<p class="small text-muted">本物の Manager と同じく、スイッチを切り替えた時点で保存されます（「保存」ボタンはありません）。</p>
<div class="card card-body oam-form" style="max-width:760px">
  <h2 class="h6">基本設定</h2>
  <div class="oam-row"><div><b>チャット</b><div class="small text-muted">オンにすると、人が手で返事をするモード。Webhook（bot）は動かなくなります（この教材の仕様）。</div></div>@include('mock.manager._toggle', ['name' => 'chat', 'on' => $oa->response_mode === 'chat', 'enabled' => true])</div>
  <div class="oam-row"><div><b>あいさつメッセージ</b><div class="small text-muted">友だち追加されたときに送るメッセージ。文面は左の「<a href="{{ route('mock.manager.oa.greeting', $oa) }}">あいさつメッセージ</a>」で設定します。</div></div>@include('mock.manager._toggle', ['name' => 'greeting_on', 'on' => $oa->greeting_on, 'enabled' => true])</div>

  <h2 class="h6 mt-3">詳細設定</h2>
  <div class="oam-row"><div><b>Webhook</b><div class="small text-muted">@if ($channel)トークのできごとを自社サーバーに知らせる。LINE Developers の「Webhookの利用」と同じスイッチです。@else Messaging API を利用すると設定できます。@endif</div></div>
    @if ($channel)@include('mock.manager._toggle', ['name' => 'webhook', 'on' => $channel->use_webhook, 'enabled' => true])@else<span class="text-muted">—</span>@endif</div>
  <div class="oam-row"><div><b>応答メッセージ</b><div class="small text-muted">LINE社が自動で返すメッセージ。キーワードごとの文面は左の「<a href="{{ route('mock.manager.oa.auto-replies', $oa) }}">応答メッセージ</a>」（いま {{ $oa->autoReplies()->count() }} 件・利用中 {{ $oa->autoReplies()->where('enabled', true)->count() }} 件）。bot（Webhook）と一緒に使うと、両方が返事をします。</div></div>@include('mock.manager._toggle', ['name' => 'auto_reply_on', 'on' => $oa->auto_reply_on, 'enabled' => true])</div>
</div>
@endsection
