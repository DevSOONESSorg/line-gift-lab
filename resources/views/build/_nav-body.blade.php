@php
  $steps = $guide->steps();
  $done = collect($steps)->where('state', 'done')->count();
  $just = $guide->announce($steps);
  $cur = collect($steps)->firstWhere('state', 'current');
@endphp
<div class="build-nav-head" onclick="toggleBuildNav()" data-progress="{{ $done }}" title="押すと、ナビをたたむ／ひらくができます">
  <span class="build-nav-title">
    <i class="bi bi-signpost-split"></i> 構築ナビ：{{ $guide->store->name }}
    <span class="badge {{ $done === count($steps) ? 'text-bg-success' : 'text-bg-light' }} ms-1">{{ $done }} / {{ count($steps) }}</span>
    @if ($cur)<span class="build-nav-now">いま：手順{{ $cur['no'] }} {{ $cur['title'] }}</span>@endif
  </span>
  <button type="button" class="build-nav-btn" onclick="event.stopPropagation(); toggleBuildNav()">
    <span class="when-open"><i class="bi bi-chevron-down"></i> たたむ</span>
    <span class="when-min"><i class="bi bi-chevron-up"></i> ひらく</span>
  </button>
</div>
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
