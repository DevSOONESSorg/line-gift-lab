@extends('mock.manager._layout')
@section('title', $oa->name)
@section('page')
<div class="d-flex align-items-center gap-3 mb-3">@include('mock._avatar', ['oa' => $oa]) <div><h1 class="h4 mb-0">{{ $oa->name }}</h1><code>{{ $oa->basic_id }}</code></div>
  <a class="btn btn-sm btn-outline-success ms-auto" href="{{ route('mock.phone', ['add' => $oa->basic_id]) }}"><i class="bi bi-qr-code"></i> お客さんのスマホで友だち追加</a></div>
<div class="row g-3 mb-3">
  @foreach (['友だち' => $friends.' 人', 'チャット' => $oa->response_mode === 'chat' ? 'オン' : 'オフ', '応答メッセージ' => $oa->auto_reply_on ? 'オン' : 'オフ',
             'Messaging API' => $channel ? '利用中' : '未設定', 'Webhook' => $channel ? ($channel->use_webhook ? 'オン' : 'オフ') : '—'] as $k => $v)
    <div class="col"><div class="card h-100"><div class="card-body py-2 px-3"><div class="small text-muted">{{ $k }}</div><strong>{{ $v }}</strong></div></div></div>
  @endforeach
</div>
<div class="card card-body small text-muted">本物の Manager のホームには、メッセージ配信・分析などが並びます。この教材では、お店の構築に使う画面（リッチメニュー・設定）だけを用意しています。</div>
@endsection
