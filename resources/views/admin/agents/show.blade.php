@extends('layouts.admin')
@section('title', $agent->name)
@section('content')
<p><a href="{{ route('admin.agents.index') }}">← 代理店管理</a></p>
<h1 class="h3 mb-3">{{ $agent->name }}</h1>
<div class="row g-3">
  <div class="col-lg-5">
    <div class="card mb-3"><div class="card-header">基本情報</div><div class="card-body"><dl class="row mb-0">
      <dt class="col-4">ID</dt><dd class="col-8">{{ $agent->id }}</dd>
      <dt class="col-4">紹介コード</dt><dd class="col-8"><code>{{ $agent->referral_code }}</code></dd>
      <dt class="col-4">階層</dt><dd class="col-8">{{ $agent->parent_id ? '2次（親：'.$agent->parent->name.'）' : '1次' }}</dd>
      <dt class="col-4">報酬率</dt><dd class="col-8">{{ $agent->parent_id ? '—（1次から受け取る）' : rtrim(rtrim($agent->reward_rate, '0'), '.').'%' }}</dd>
      <dt class="col-4">ステータス</dt><dd class="col-8">{{ $agent->is_active ? '有効' : '無効' }}</dd>
      <dt class="col-4">登録日</dt><dd class="col-8">{{ $agent->created_at->format('Y/m/d') }}</dd>
    </dl></div></div>
    <div class="card mb-3"><div class="card-header">報酬サマリー（{{ $year }}年）</div><div class="card-body"><dl class="row mb-0">
      <dt class="col-6">直接紹介店舗数</dt><dd class="col-6">{{ $directCount }}</dd>
      <dt class="col-6">2次代理店経由</dt><dd class="col-6">{{ $viaSecondCount }}</dd>
      <dt class="col-6">合計店舗数</dt><dd class="col-6">{{ $directCount + $viaSecondCount }}</dd>
      <dt class="col-6">売上</dt><dd class="col-6">¥{{ number_format($sales) }}</dd>
      <dt class="col-6">報酬額 ({{ rtrim(rtrim($agent->reward_rate, '0'), '.') }}%)</dt><dd class="col-6">¥{{ number_format($reward) }}</dd>
    </dl><p class="small text-muted mb-0">※ 紐づけ店舗の「売上」に対する報酬額（手数料ではなく売上 × 報酬率）</p></div></div>
  </div>
  <div class="col-lg-7">
    <div class="card mb-3"><div class="card-header">紐づけ店舗一覧</div><table class="table mb-0"><thead><tr><th>店舗名</th><th>スラッグ</th><th>ステータス</th><th>経由</th></tr></thead><tbody>
      @php $rows = $agent->stores->map(fn ($s) => [$s, '直接']); foreach ($agent->children as $c) foreach ($c->stores as $s) $rows->push([$s, "2次：{$c->name}"]); @endphp
      @forelse ($rows as [$s, $via])<tr><td><a href="{{ route('admin.stores.show', $s) }}">{{ $s->name }}</a></td><td><code>{{ $s->slug }}</code></td><td>{{ $s->is_approved ? '承認済み' : '未承認' }}</td><td>{{ $via }}</td></tr>
      @empty<tr><td colspan="4" class="text-muted text-center">紐づけ店舗がありません</td></tr>@endforelse
    </tbody></table></div>
    <div class="card"><div class="card-header">月別売上・報酬（{{ $year }}年）</div><table class="table table-sm mb-0"><thead><tr><th>月</th><th>売上</th><th>報酬</th></tr></thead><tbody>
      @for ($m = 1; $m <= 12; $m++)
        @php $s = (int) ($monthly[$m] ?? 0); @endphp
        <tr><td>{{ $m }}月</td><td>¥{{ number_format($s) }}</td><td>¥{{ number_format($agent->parent_id ? 0 : round($s * $agent->reward_rate / 100)) }}</td></tr>
      @endfor
    </tbody></table></div>
  </div>
</div>
@endsection
