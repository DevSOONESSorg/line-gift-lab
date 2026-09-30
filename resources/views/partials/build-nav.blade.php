{{-- 画面の右下に出る構築ナビ（どのお店を構築中か選んでいるときだけ） --}}
@php $guide = \App\Services\BuildGuide::current(); @endphp
@if ($guide && ! request()->routeIs('build'))
  @php
    $steps = $guide->steps(); $ci = $guide->currentIndex($steps); $done = collect($steps)->where('state', 'done')->count();
    $cur = $ci !== null ? $steps[$ci] : null;
    $me = session('mock_account', 'personal');
    $needs = $cur ? ($cur['needs'] ?? $cur['who']) : null;
  @endphp
  <div class="build-nav" id="buildnav">
    <button class="build-nav-toggle" onclick="document.getElementById('buildnav').classList.toggle('min'); try{localStorage.setItem('buildnav-min', document.getElementById('buildnav').classList.contains('min') ? '1' : '')}catch(e){}">
      <i class="bi bi-signpost-split"></i> 構築ナビ：{{ $guide->store->name }} <span class="badge text-bg-light">{{ $done }}/{{ count($steps) }}</span></button>
    <div class="build-nav-body">
      @if ($cur)
        @if (in_array($needs, ['personal', 'company'], true) && $needs !== $me)
          <form method="post" action="{{ route('mock.switch-account') }}" class="alert alert-warning py-1 px-2 small mb-2">@csrf
            <input type="hidden" name="account_id" value="{{ $needs }}"><input type="hidden" name="back" value="{{ str_starts_with(request()->getRequestUri(), '/mock/') ? request()->getRequestUri() : '/mock/manager' }}">
            この手順は <strong>{{ \App\Services\BuildGuide::ACCOUNTS[$needs] }}</strong> で行います。<button class="btn btn-sm btn-warning py-0 ms-1">切り替える</button></form>
        @endif
        @include('build._step', ['st' => $cur, 'open' => true])
      @else
        <div class="alert alert-success small mb-2">すべての手順が終わりました 🎉 お客さんのスマホで贈って、オーナーのスマホで受け取ってみましょう。</div>
      @endif
      <a class="small" href="{{ route('build') }}">すべての手順を見る →</a>
    </div>
  </div>
  <script>try { if (localStorage.getItem('buildnav-min')) document.getElementById('buildnav').classList.add('min'); } catch (e) {}</script>
@endif
