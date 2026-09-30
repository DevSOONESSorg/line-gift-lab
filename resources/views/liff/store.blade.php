@extends('layouts.liff')
@section('title', $store->name)
@section('content')
  @if ($route === 'common')<p class="small mb-1"><a href="{{ route('liff.shops') }}">← お店をさがす</a></p>@endif
  <div class="l-title">{{ $store->name }}</div>
  <p class="l-lead">{{ $store->description }}</p>
  <p class="small text-center" style="color:var(--muted)">ようこそ <span id="who">{{ $lineUser['name'] ?? '（LINEで確認中…）' }}</span> さん</p>

  @forelse ($menus as $m)
    <div class="l-card">
      <div class="name">{{ $m->name }}</div>
      <div class="price">¥{{ number_format($m->price) }}</div>
      <a class="btn-line-gold" href="{{ route('liff.order.form', [$store->slug, $m]) }}">この商品を送る</a>
    </div>
  @empty
    <p class="l-empty">このお店にはまだ商品がありません。</p>
  @endforelse

  @if ($store->line_official_account_id && $route === 'original')
    <p class="small text-center mt-3"><a href="{{ ($lineUser['mock'] ?? false) ? route('mock.phone.screen', ['phone' => $lineUser['phone'] ?? 'customer', 'add' => $store->line_official_account_id]) : 'https://line.me/R/ti/p/'.$store->line_official_account_id }}" target="{{ ($lineUser['mock'] ?? false) ? '_parent' : '_top' }}">このお店の公式LINEを友だち追加</a></p>
  @endif
  @include('liff._tabbar', ['home' => route('liff.store', $route === 'common' ? [$store->slug, 'via' => 'app'] : $store->slug)])
@endsection
@push('scripts')
@if (! $lineUser && $store->liff_id)
{{-- 本物のLINEで開かれたとき：LIFF SDK で「誰が開いたか」を取り出してサーバーに伝える --}}
<script charset="utf-8" src="https://static.line-scdn.net/liff/edge/2/sdk.js"></script>
<script>
  liff.init({ liffId: @json($store->liff_id) }).then(async () => {
    if (!liff.isLoggedIn()) return liff.login();
    const p = await liff.getProfile();
    await fetch(@json(route('liff.session')), { method: 'POST', headers: { 'Content-Type': 'application/json', 'X-CSRF-TOKEN': @json(csrf_token()) }, body: JSON.stringify({ userId: p.userId, displayName: p.displayName }) });
    location.reload();
  }).catch((e) => { document.getElementById('who').textContent = '（LIFFの準備に失敗：' + e.message + '）'; });
</script>
@endif
@endpush
