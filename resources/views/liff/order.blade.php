@extends('layouts.liff')
@section('title', '以下の商品を送ります')
@section('content')
  <p class="text-center mb-3">以下の商品を送ります</p>
  <div class="l-title">{{ $menu->name }}</div>
  <div class="text-center fw-bold fs-3 mb-3" style="color:var(--price)">¥{{ number_format($menu->price) }}</div>
  <p class="small text-center" style="color:var(--muted)">{{ $store->name }}</p>

  <form method="post" action="{{ route('liff.order', $store->slug) }}" enctype="multipart/form-data">
    @csrf
    <input type="hidden" name="menu_id" value="{{ $menu->id }}">

    <div class="l-label">送信者名（あなたのお名前）</div>
    <input name="sender_name" class="l-input" value="{{ old('sender_name', $customer?->name ?: ($lineUser['name'] ?? '')) }}" maxlength="50">

    <div class="l-label">贈る人（任意）</div>
    <input name="recipient_name" class="l-input" placeholder="空欄の場合はお店宛になります" value="{{ old('recipient_name') }}" maxlength="50">

    <div class="l-label">メッセージ（任意）</div>
    <textarea name="message" class="l-textarea" placeholder="自由にメッセージを入力できます">{{ old('message') }}</textarea>

    @if ($needsId)
      <div class="l-card mt-4">
        <div class="fw-bold mb-1">身分証の登録</div>
        <p class="small" style="color:var(--muted)">このお店は身分証の提示が必要です。一度登録すると、ほかのお店でも使えます。</p>
        <input type="file" name="id_image" accept="image/*" class="form-control">
      </div>
    @endif

    <div class="l-card mt-4">
      <div class="mb-3" style="color:var(--muted)">お支払い方法</div>
      <div class="pay-toggle">
        <label><input type="radio" name="payment_method" value="card" @checked(old('payment_method', 'card') === 'card') onchange="toggle()"><span>クレジットカード</span></label>
        <label><input type="radio" name="payment_method" value="bank_transfer" @checked(old('payment_method') === 'bank_transfer') onchange="toggle()"><span>銀行振込</span></label>
      </div>
    </div>

    <div class="l-card" id="cardbox">
      @if ($customer?->hasCard())
        <div class="mb-3" style="color:var(--muted)">登録済みカード</div>
        <label class="card-opt"><input type="radio" name="use_saved" value="1" @checked(old('use_saved', '1') === '1') onchange="toggle()"><span>VISA&nbsp; **** {{ $customer->card_last4 }}</span><span class="exp">{{ $customer->card_exp }}</span></label>
        <label class="card-opt" id="addcard"><input type="radio" name="use_saved" value="0" @checked(old('use_saved') === '0') onchange="toggle()"><span>＋ 新しいカードを追加</span></label>
      @else
        <div class="mb-2" style="color:var(--muted)">カードを登録</div>
        <p class="small" style="color:var(--muted)">はじめての方は、カードを登録します（次回からは入力不要）。</p>
      @endif
      <div id="newcard">
        <input name="card_number" class="l-input mb-2" placeholder="カード番号 4242 4242 4242 4242" value="{{ old('card_number') }}" inputmode="numeric">
        <div class="d-flex gap-2"><input name="exp" class="l-input" placeholder="有効期限 12/40" value="{{ old('exp') }}"><input name="cvc" class="l-input" placeholder="CVC 123" value="{{ old('cvc') }}" inputmode="numeric"></div>
      </div>
      <p class="small mt-3 mb-0" style="color:var(--muted)">「受け取る」前はカードの枠を仮押さえするだけで、お金は動きません。{{ config('lab.card_authorization_days') }}日以内にお店が受け取らないと自動で取り消されます。</p>
    </div>
    <div class="l-card" id="bankbox"><p class="small mb-0" style="color:var(--muted)">注文後に振込先をお知らせします。{{ config('lab.bank_transfer_days') }}日以内にお振込みください。入金を確認したら、お店にお届けします。</p></div>

    <p class="l-note">注文を確定することで、利用規約および特定商取引法に基づく表記に同意したものとみなします。（体験用のテスト決済です。本物のお金は動きません）</p>
    <button class="btn-gold" id="paybtn">ギフトを贈る</button>
  </form>
  <div class="l-warn"><strong>20歳未満の方への酒類の販売は行っておりません</strong>アルコール飲料を含む商品を取り扱っています。</div>
  @include('liff._tabbar', ['home' => route('liff.store', $store->slug)])
@endsection
@push('scripts')
<script>
  const last4 = @json($customer?->hasCard() ? $customer->card_last4 : null);
  function toggle() {
    const card = document.querySelector('[name=payment_method][value=card]').checked;
    document.getElementById('cardbox').style.display = card ? '' : 'none';
    document.getElementById('bankbox').style.display = card ? 'none' : '';
    const saved = document.querySelector('[name=use_saved][value="1"]');
    const useSaved = saved && saved.checked;
    document.getElementById('newcard').style.display = useSaved ? 'none' : '';
    document.getElementById('paybtn').textContent = !card ? '銀行振込で注文する' : useSaved ? `VISA ****${last4} で決済する` : 'カードを登録して決済する';
  }
  toggle();
</script>
@endpush
