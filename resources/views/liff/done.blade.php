@extends('layouts.liff')
@section('title', 'ありがとうございました')
@section('content')
  <div class="done-mark"><i class="bi bi-check-lg"></i></div>
  @if ($order->status === \App\Enums\OrderStatus::AwaitingPayment)
    <div class="done-kicker">ORDERED</div>
    <div class="l-title">ご注文ありがとうございます</div>
    <p class="text-center" style="color:var(--muted)">{{ $order->expires_at->format('m/d H:i') }} までに <strong style="color:var(--text)">¥{{ number_format($order->amount) }}</strong> をお振込みください。<br>振込先：{{ config('lab.bank_transfer_account') }}<br>期限を過ぎると自動でキャンセルされます。</p>
  @else
    <div class="done-kicker">DELIVERED</div>
    <div class="l-title">ありがとうございました</div>
    <p class="text-center" style="color:var(--muted)">贈り物をお店にお届けしました。<br>お礼が届いたらLINEでお知らせします。</p>
  @endif
  <div class="order-no"><small>ORDER NO</small><b>{{ $order->order_code }}</b></div>

  <a class="btn-gold" href="{{ route('liff.history') }}">贈った履歴を見る</a>
  @if (($lineUser['mock'] ?? false) && $openedLiff && $order->store->line_official_account_id && $order->route === 'original')
    <button class="btn-ghost" onclick="sendToChat(this)">トークに「贈りました」と送る（liff.sendMessages）</button>
  @endif
  <button class="btn-ghost" onclick="closeLiff()">閉じる</button>
@endsection
@push('scripts')
<script>
  // 本物では liff.sendMessages([...]) で、お客さん本人の発言としてトークに送れる（scope に chat_message.write が必要）
  async function sendToChat(btn) {
    const r = await fetch(@json(route('mock.phone.liff-send')), { method: 'POST', headers: { 'Content-Type': 'application/json', 'X-CSRF-TOKEN': @json(csrf_token()) },
      body: JSON.stringify({ mock_uid: @json($mockUid ?? null), liff_id: @json($openedLiff), basic_id: @json($order->store->line_official_account_id), text: @json("「{$order->menu_name}」を贈りました🎁") }) }).then((x) => x.json());
    btn.textContent = r.ok ? '送りました' : '送れませんでした：' + r.message;
    btn.disabled = true;
  }
  // 本物では liff.closeWindow()。疑似スマホでは、スマホの「✕」を押したのと同じにする
  function closeLiff() {
    try { const x = window.parent.document.querySelector('.liff-close'); if (x) return x.click(); } catch (e) {}
    if (window.liff) liff.closeWindow();
  }
</script>
@endpush
