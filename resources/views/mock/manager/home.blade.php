@extends('mock.manager._layout')
@section('title', $oa->name)
@section('page')
<h1 class="h4">{{ $oa->name }}</h1>
<div class="d-flex flex-wrap gap-2 mb-3">
  @foreach (['友だち' => $friends, '応答モード' => $oa->response_mode === 'bot' ? 'Bot' : 'チャット', '応答メッセージ' => $oa->auto_reply_on ? 'ON' : 'OFF',
             'Messaging API' => $channel ? '有効' : '未設定', 'Webhook' => $channel ? ($channel->use_webhook ? 'ON' : 'OFF') : '—'] as $k => $v)
    <div class="card"><div class="card-body py-2 px-3"><div class="small text-muted">{{ $k }}</div><strong>{{ $v }}</strong></div></div>
  @endforeach
</div>
<p>ベーシックID：<code>{{ $oa->basic_id }}</code> <a class="btn btn-sm btn-outline-success" href="{{ route('mock.phone', ['add' => $oa->basic_id]) }}">お客さんのスマホで友だち追加</a></p>
@endsection
