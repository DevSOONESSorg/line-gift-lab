@extends('layouts.liff')
@section('title', $store->name)
@section('content')
  <div class="store-head" style="background:{{ $store->image_color }}">
    <div class="store-name">{{ $store->name }}</div>
    <div class="small">ようこそ <span id="who">{{ $lineUser['name'] ?? '（LINEで確認中…）' }}</span> さん</div>
  </div>
  @if ($route === 'common')<p class="small text-muted"><a href="{{ route('liff.shops') }}">← お店をさがす</a>（共通アプリから開いています）</p>@endif
  @if ($store->description)<p class="small">{{ $store->description }}</p>@endif

  @if ($menus->isEmpty())
    <div class="alert alert-warning">このお店にはまだ商品がありません。</div>
  @else
  <form method="post" action="{{ route('liff.order', $store->slug) }}" enctype="multipart/form-data">
    @csrf
    <h2 class="h6 mt-3">1. ギフトを選ぶ</h2>
    @foreach ($menus as $m)
      <label class="menu"><input type="radio" name="menu_id" value="{{ $m->id }}" @checked(old('menu_id') == $m->id)>
        <span class="flex-grow-1">{{ $m->name }}</span><strong>¥{{ number_format($m->price) }}</strong></label>
    @endforeach

    <h2 class="h6 mt-3">2. メッセージ（お店・スタッフへ）</h2>
    <textarea name="message" class="form-control" rows="2" placeholder="例：今日はみんなで乾杯してください！">{{ old('message') }}</textarea>

    @if ($needsId)
      <h2 class="h6 mt-3">身分証の登録</h2>
      <p class="small">このお店は身分証の提示が必要です。一度登録すると、ほかのお店でも使えます。</p>
      <input type="file" name="id_image" accept="image/*" class="form-control">
    @endif

    <h2 class="h6 mt-3">3. お支払い</h2>
    <label class="menu"><input type="radio" name="payment_method" value="card" @checked(old('payment_method', 'card') === 'card') onchange="toggle()"><span>クレジットカード</span></label>
    <label class="menu"><input type="radio" name="payment_method" value="bank_transfer" @checked(old('payment_method') === 'bank_transfer') onchange="toggle()"><span>銀行振込（{{ config('lab.bank_transfer_days') }}日以内）</span></label>

    <div id="cardbox" class="mt-2">
      @if ($customer?->hasCard())
        <label class="menu"><input type="radio" name="use_saved" value="1" @checked(old('use_saved', '1') === '1') onchange="toggle()"><span>登録済みのカード **** {{ $customer->card_last4 }}（{{ $customer->card_exp }}）</span></label>
        <label class="menu"><input type="radio" name="use_saved" value="0" @checked(old('use_saved') === '0') onchange="toggle()"><span>別のカードを登録する</span></label>
      @else
        <p class="small text-muted mb-1">はじめての方は、カードを登録します（次回からは入力不要）。</p>
      @endif
      <div id="newcard">
        <input name="card_number" class="form-control mb-2" placeholder="カード番号 4242 4242 4242 4242" value="{{ old('card_number') }}" inputmode="numeric">
        <div class="d-flex gap-2"><input name="exp" class="form-control" placeholder="有効期限 12/40" value="{{ old('exp') }}"><input name="cvc" class="form-control" placeholder="CVC 123" value="{{ old('cvc') }}" inputmode="numeric"></div>
      </div>
      <p class="small text-muted mt-2">「受け取る」前はカードの枠を仮押さえするだけで、お金は動きません。{{ config('lab.card_authorization_days') }}日以内にお店が受け取らないと自動で取り消されます。</p>
    </div>
    <button class="btn btn-success w-100 btn-lg mt-3">ギフトを贈る</button>
    <p class="small text-muted text-center mt-2">体験用のテスト決済です。本物のお金は動きません。</p>
  </form>
  @endif

  @if ($store->line_official_account_id && $route === 'original')
    <p class="small text-center"><a href="{{ ($lineUser['mock'] ?? false) ? route('mock.phone', ['add' => $store->line_official_account_id]) : 'https://line.me/R/ti/p/'.$store->line_official_account_id }}" target="_top">このお店の公式LINEを友だち追加</a></p>
  @endif
@endsection
@push('scripts')
<script>
  function toggle() {
    const card = document.querySelector('[name=payment_method][value=card]').checked;
    document.getElementById('cardbox').style.display = card ? '' : 'none';
    const saved = document.querySelector('[name=use_saved][value="1"]');
    document.getElementById('newcard').style.display = saved && saved.checked ? 'none' : '';
  }
  toggle();
</script>
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
