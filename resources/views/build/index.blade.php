@extends('layouts.lab')
@section('title', '構築ナビ')
@section('content')
<h1 class="h4"><i class="bi bi-signpost-split"></i> 構築ナビ</h1>
<p class="text-muted">お店の公式LINEと自社サービス（おくりギフト）を、<strong>本番と同じ順番</strong>でつなぐ道案内です。
  それぞれの手順が終わったかは、あなたの入力ではなく <strong>実際の設定を見て自動で判定</strong> します。<strong>前の手順が終わるまで、次の手順は開きません。</strong></p>

<form method="post" action="{{ route('build.select') }}" class="card card-body mb-4" onsubmit="return confirm('このお店を最初の状態に戻して、新しく構築を始めます。これまでの構築（公式LINEの設定・LIFF・注文など）はすべて消えます。よろしいですか？')">@csrf
  <div class="d-flex flex-wrap gap-2 align-items-center">
    <label class="fw-bold text-nowrap">構築するお店</label>
    <select name="scenario" class="form-select w-auto">
      @foreach ($scenarios as $k => $sc)<option value="{{ $k }}" @selected($guide?->scenario === $k)>{{ $sc['label'] }}</option>@endforeach
    </select>
    <button class="btn btn-primary"><i class="bi bi-arrow-counterclockwise"></i> 最初から構築を始める</button>
  </div>
  <div class="form-text">選ぶと、そのお店は「オーナーが出店登録した直後・公式LINEとリッチメニューはあるが、まだつながっていない」状態に戻ります。お客さんのスマホにお店の公式LINEが出ますが、手順10が終わるまでリッチメニューは押せません。</div>
</form>

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
        <h2 class="h6">このお店の値（いまの状態）</h2>
        <dl class="mb-0">
          <dt>slug（決める値）</dt><dd><code>{{ $guide->targetSlug() }}</code></dd>
          <dt>公式アカウント</dt><dd>{{ $guide->oa ? $guide->oa->name.'（'.$guide->oa->basic_id.'）' : '—' }}</dd>
          <dt>Messaging API チャネル</dt><dd>{{ $guide->messaging?->channel_id ?? '—' }}</dd>
          <dt>LINEログインチャネル</dt><dd>{{ $guide->login ? $guide->login->channel_id.'（'.($guide->login->is_published ? '公開済み' : '開発中').'）' : '—' }}</dd>
          <dt>LIFF ID</dt><dd>{{ $guide->liff?->liff_id ?? '—' }}</dd>
          <dt>LIFF のエンドポイントURL（正解）</dt><dd><code class="user-select-all">{{ $guide->liffUrl() }}</code></dd>
          <dt>Webhook URL（正解）</dt><dd><code class="user-select-all">{{ $guide->webhookUrl() }}</code></dd>
        </dl>
      </div></div>
      <p class="small text-muted mt-2">管理画面・Manager・LINE Developers・疑似スマホの右下にも、同じナビが出ます（見出しを押すと小さくできます）。</p>
    </div>
  </div>
@endif
@endsection
