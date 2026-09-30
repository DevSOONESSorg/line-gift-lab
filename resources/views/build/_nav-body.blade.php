@php
  $steps = $guide->steps();
  $done = collect($steps)->where('state', 'done')->count();
  $just = $guide->announce($steps);
  $cur = collect($steps)->firstWhere('state', 'current');
@endphp
<button class="build-nav-toggle" onclick="toggleBuildNav()" data-progress="{{ $done }}">
  <i class="bi bi-signpost-split"></i> 構築ナビ：{{ $guide->store->name }}
  <span class="badge {{ $done === count($steps) ? 'text-bg-success' : 'text-bg-light' }} ms-1">{{ $done }} / {{ count($steps) }}</span>
  <i class="bi bi-chevron-down float-end"></i>
</button>
<div class="build-nav-body">
  @if ($just)
    <div class="bjust"><i class="bi bi-check-circle-fill"></i> 手順{{ $just['no'] }}「{{ $just['title'] }}」が <b>完了</b> しました！@if ($cur) 次は手順{{ $cur['no'] }}です。@endif</div>
  @endif
  @if (! $cur)
    <div class="bjust"><i class="bi bi-trophy-fill"></i> すべての手順が完了しました。お客さんのスマホで贈り、オーナーのスマホで受け取ってみましょう。</div>
  @endif
  @foreach ($steps as $st)@include('build._step', ['st' => $st])@endforeach
  <a class="small" href="{{ route('build') }}">構築ナビのページを開く（お店を選び直す・最初からやり直す）→</a>
</div>
