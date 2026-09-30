@extends('layouts.lab')
@section('title', '構築ナビ')
@section('content')
<h1 class="h4"><i class="bi bi-signpost-split"></i> 構築ナビ</h1>
<p class="text-muted">お店の公式LINEと自社サービス（おくりギフト）を、<strong>本番と同じ順番</strong>でつなぐ道案内です。
  それぞれの手順が終わったかは、あなたの入力ではなく <strong>実際の設定を見て自動で判定</strong> します。<strong>前の手順が終わるまで、次の手順は開きません。</strong></p>

<div class="card card-body mb-4">
  <form method="post" action="{{ route('build.select') }}" id="scenario-form" class="d-flex flex-wrap gap-2 align-items-center">@csrf
    <label class="fw-bold text-nowrap" for="scenario-select">構築するお店</label>
    <select name="scenario" id="scenario-select" class="form-select w-auto" onchange="this.form.submit()">
      @unless ($guide?->scenario)<option value="" selected disabled>— お店を選んでください —</option>@endunless
      @foreach ($scenarios as $k => $sc)<option value="{{ $k }}" @selected($guide?->scenario === $k)>{{ $sc['label'] }}（{{ $status[$k]['label'] }}）</option>@endforeach
    </select>
  </form>
  <div class="form-text">
    はじめて選んだお店は、「オーナーが出店登録した直後・お店の公式LINEとリッチメニューはあるが、まだつながっていない」状態で用意され、<strong>お客さんのスマホにお店の公式LINEが追加</strong>されます（「贈る」ボタンは、リッチメニューの設定が終わるまで押せません）。<br>
    ほかのお店に切り替えても、構築したものはそのまま残ります。完成したお店は、いつでもお客さんのスマホから贈れます。
  </div>
  <form method="post" action="{{ route('build.reset') }}" class="mt-2" onsubmit="return confirm('構築履歴をリセットします。\n課題のお店（{{ collect($scenarios)->pluck('store.name')->join('・') }}）の構築したもの（お店の公式LINE・チャネル・LIFF・注文など）がすべて消え、お客さんのスマホからも公式LINEがなくなります。よろしいですか？')">@csrf
    <button class="btn btn-outline-danger btn-sm"><i class="bi bi-trash3"></i> 構築履歴をリセット</button>
    <span class="small text-muted ms-1">課題のお店をすべて消して、だれも構築していない最初の状態に戻します</span>
  </form>
</div>

@if ($guide)
  @php $done = collect($steps)->where('state', 'done')->count(); @endphp
  <div class="d-flex align-items-center gap-3 mb-3">
    <h2 class="h5 mb-0">{{ $guide->store->name }}</h2>
    <div class="progress flex-grow-1" style="height:10px"><div class="progress-bar bg-success" style="width:{{ round($done / count($steps) * 100) }}%"></div></div>
    <strong>{{ $done }} / {{ count($steps) }}</strong>
  </div>
  @php $just = $guide->announce($steps); $cur = collect($steps)->firstWhere('state', 'current'); @endphp
  @if ($just)<div class="bjust mb-3"><i class="bi bi-check-circle-fill"></i> 手順{{ $just['no'] }}「{{ $just['title'] }}」が <b>完了</b> しました！@if ($cur) 次は手順{{ $cur['no'] }}です。@endif</div>@endif
  @if (! $cur)<div class="bjust mb-3"><i class="bi bi-trophy-fill"></i> すべての手順が完了しました。</div>@endif
  <div class="row g-4">
    <div class="col-lg-8">
      @foreach ($steps as $st)@include('build._step', ['st' => $st])@endforeach
    </div>
    <div class="col-lg-4">
      <div class="card"><div class="card-body small">
        <h2 class="h6">このお店の進み具合</h2>
        <p class="text-muted mb-2">ID やトークンの「正解」はここには出しません。本番と同じく、LINE Developers・Manager・管理画面の <b>コピー</b> から取ってください。</p>
        <dl class="mb-0">
          <dt>slug（決める値）</dt><dd><code>{{ $guide->targetSlug() }}</code></dd>
          <dt>公式アカウント</dt><dd>{{ $guide->oa ? $guide->oa->name : '—' }}</dd>
          <dt>Messaging API チャネル</dt><dd>{{ $guide->messaging ? '作成済み' : '—' }}</dd>
          <dt>LINEログインチャネル</dt><dd>{{ $guide->login ? ($guide->login->is_published ? '公開済み' : '開発中') : '—' }}</dd>
          <dt>LIFF アプリ</dt><dd>{{ $guide->liff ? '作成済み（'.$guide->liff->size.'）' : '—' }}</dd>
          @if ($sn = $guide->snapshot())<dt>控えた「変更前の状態」</dt><dd>応答メッセージ {{ $sn['auto_reply_on'] ? 'ON' : 'OFF' }}／キーワード応答 {{ $sn['keywords'] }}件／あいさつ {{ $sn['greeting_on'] ? 'ON' : 'OFF' }}／ケース{{ $sn['case'] }}</dd>@endif
        </dl>
      </div></div>
      <p class="small text-muted mt-2">管理画面・Manager・LINE Developers・疑似スマホの右下にも、同じナビが出ます（見出しを押すと小さくできます）。</p>
    </div>
  </div>
@else
  <div class="alert alert-info"><i class="bi bi-arrow-up"></i> 上のプルダウンで、構築するお店を選んでください。選ぶと、ここに手順（17ステップ）が出ます。</div>
@endif
@endsection
