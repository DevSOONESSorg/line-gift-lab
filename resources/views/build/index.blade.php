@extends('layouts.lab')
@section('title', '構築ナビ')
@section('content')
<h1 class="h4"><i class="bi bi-signpost-split"></i> 構築ナビ</h1>
<p class="text-muted">オリジナル（お店専用の公式LINE）を希望したお店を、<strong>本番と同じ順番</strong>でつなぐ道案内です。
  それぞれの手順が終わったかは、あなたの入力ではなく <strong>実際の設定（疑似LINE・管理画面）を見て自動で判定</strong> します。値を間違えると、その手順は「まだ」のままです。</p>

<form method="post" action="{{ route('build.select') }}" class="d-flex gap-2 align-items-center mb-4">@csrf
  <label class="text-nowrap">構築するお店</label>
  <select name="store_id" class="form-select w-auto">
    @foreach ($stores as $s)<option value="{{ $s->id }}" @selected($guide?->store->id === $s->id)>{{ $s->name }}（{{ $s->slug }}）{{ $s->wants_original ? '・オリジナル希望' : '' }}</option>@endforeach
  </select>
  <button class="btn btn-primary">このお店で始める</button>
</form>

@if ($guide)
  @php $done = collect($steps)->where('state', 'done')->count(); @endphp
  <div class="d-flex align-items-center gap-3 mb-3">
    <div class="progress flex-grow-1" style="height:10px"><div class="progress-bar bg-success" style="width:{{ round($done / count($steps) * 100) }}%"></div></div>
    <strong>{{ $done }} / {{ count($steps) }}</strong>
  </div>
  <div class="row g-4">
    <div class="col-lg-8">
      @foreach ($steps as $st)@include('build._step', ['st' => $st, 'open' => true])@endforeach
    </div>
    <div class="col-lg-4">
      <div class="card"><div class="card-body small">
        <h2 class="h6">いま見ている値</h2>
        <dl class="mb-0">
          <dt>公式アカウント</dt><dd>{{ $guide->oa ? $guide->oa->name.'（'.$guide->oa->basic_id.'）' : '—' }}</dd>
          <dt>Messaging API チャネル</dt><dd>{{ $guide->messaging?->channel_id ?? '—' }}</dd>
          <dt>LINEログインチャネル</dt><dd>{{ $guide->login ? $guide->login->channel_id.'（'.($guide->login->is_published ? '公開済み' : '開発中').'）' : '—' }}</dd>
          <dt>LIFF ID</dt><dd>{{ $guide->liff?->liff_id ?? '—' }}</dd>
          <dt>LIFF のエンドポイントURL（正解）</dt><dd><code class="user-select-all">{{ $guide->liffUrl() }}</code></dd>
          <dt>Webhook URL（正解）</dt><dd><code class="user-select-all">{{ $guide->webhookUrl() }}</code></dd>
        </dl>
      </div></div>
      <p class="small text-muted mt-2">画面の右下の「構築ナビ」は、Manager・LINE Developers・管理画面のどこにいても、いまの手順を表示します。</p>
      <form method="post" action="{{ route('build.stop') }}">@csrf<button class="btn btn-sm btn-link text-muted p-0">構築ナビを終わる</button></form>
    </div>
  </div>
@endif
@endsection
