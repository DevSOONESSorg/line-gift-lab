@extends('layouts.admin')
@section('title', '売上集計')
@section('content')
<h1 class="h3 mb-3">売上集計</h1>
<form class="row g-2 mb-3 align-items-end">
  <div class="col-auto"><label class="form-label small">開始日</label><input type="date" name="start_date" class="form-control" value="{{ $start }}"></div>
  <div class="col-auto"><label class="form-label small">終了日</label><input type="date" name="end_date" class="form-control" value="{{ $end }}"></div>
  <div class="col-auto"><button class="btn btn-primary">集計</button> <a class="btn btn-outline-secondary" href="{{ route('admin.sales') }}">今月</a></div>
</form>
<div class="row g-3 mb-3">
  <div class="col-md-4"><div class="card stat"><div class="card-body"><div class="text-muted small">期間内注文数</div><div class="fs-4">{{ $summary->count }}件</div></div></div></div>
  <div class="col-md-4"><div class="card stat"><div class="card-body"><div class="text-muted small">期間内売上</div><div class="fs-4">¥{{ number_format($summary->sales) }}</div></div></div></div>
  <div class="col-md-4"><div class="card stat"><div class="card-body"><div class="text-muted small">期間内手数料</div><div class="fs-4">¥{{ number_format($summary->commission) }}</div></div></div></div>
</div>
<p class="small text-muted">「受取済み」「お礼済み」の注文だけを売上として数えます（リクエスト中・期限切れ・返金は含みません）。</p>
<div class="card mb-3"><div class="card-header">店舗別売上</div><table class="table mb-0"><thead><tr><th>店舗名</th><th>注文数</th><th>売上</th><th>手数料</th><th>振込額</th></tr></thead><tbody>
  @forelse ($byStore as $r)<tr><td>{{ $r->name }}</td><td>{{ $r->count }}</td><td>¥{{ number_format($r->sales) }}</td><td>¥{{ number_format($r->commission) }}</td><td>¥{{ number_format($r->payout) }}</td></tr>
  @empty<tr><td colspan="5" class="text-center text-muted">期間内の売上はありません</td></tr>@endforelse
</tbody></table>
  <details class="card-footer small"><summary>この表を作っている SQL</summary><pre class="mb-0 mt-2 sql">{{ $sql }}</pre></details>
</div>
<div class="card mb-3"><div class="card-header">店舗別手数料率履歴</div><div class="card-body">
  <form class="row g-2 mb-2"><input type="hidden" name="start_date" value="{{ $start }}"><input type="hidden" name="end_date" value="{{ $end }}">
    <div class="col-auto"><select name="store_id" class="form-select"><option value="">-- 店舗を選択 --</option>
      @foreach ($stores as $s)<option value="{{ $s->id }}" @selected(request('store_id') == $s->id)>{{ $s->name }} (デフォルト: {{ rtrim(rtrim($s->commission_rate, '0'), '.') }}%)</option>@endforeach</select></div>
    <div class="col-auto"><button class="btn btn-outline-primary">表示</button></div></form>
  @if ($rateStore)
    <table class="table table-sm"><thead><tr><th>月</th><th>手数料率</th></tr></thead><tbody>
      @forelse ($rateStore->monthlyRates->sortBy(['year', 'month']) as $mr)<tr><td>{{ $mr->year }}年{{ $mr->month }}月</td><td>{{ rtrim(rtrim($mr->rate, '0'), '.') }}%</td></tr>
      @empty<tr><td colspan="2" class="text-muted">月別の設定はありません（すべてデフォルト {{ rtrim(rtrim($rateStore->commission_rate, '0'), '.') }}%）</td></tr>@endforelse
    </tbody></table>
  @else <p class="text-muted small mb-0">店舗を選択して手数料率履歴を表示</p> @endif
</div></div>
<div class="card"><div class="card-header">月別推移</div><table class="table mb-0"><thead><tr><th>月</th><th>注文数</th><th>売上</th><th>手数料</th></tr></thead><tbody>
  @forelse ($monthly as $m)<tr><td>{{ $m->month }}</td><td>{{ $m->count }}</td><td>¥{{ number_format($m->sales) }}</td><td>¥{{ number_format($m->commission) }}</td></tr>
  @empty<tr><td colspan="4" class="text-center text-muted">まだありません</td></tr>@endforelse
</tbody></table></div>
@endsection
