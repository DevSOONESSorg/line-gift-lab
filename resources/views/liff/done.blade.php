@extends('layouts.liff')
@section('title', 'ありがとうございました')
@section('content')
  <h1 class="h5 mt-3">ありがとうございました</h1>
  <p><strong>{{ $order->store->name }}「{{ $order->menu_name }}」</strong>（注文番号 #{{ $order->id }}）</p>
  @if ($order->status === \App\Enums\OrderStatus::AwaitingPayment)
    <div class="alert alert-warning small">いまの状態は <strong>入金待ち</strong> です。{{ $order->expires_at->format('m/d H:i') }} までに <strong>¥{{ number_format($order->amount) }}</strong> をお振込みください。<br>振込先：{{ config('lab.bank_transfer_account') }}<br>期限を過ぎると自動でキャンセルされます。</div>
  @else
    <div class="alert alert-info small">いまの状態は <strong>リクエスト中</strong> です。まだ決済は確定していません。お店が「受け取る」を押すと確定し、お礼が届きます。</div>
  @endif
  @if (($lineUser['mock'] ?? false) && $openedLiff && $order->store->line_official_account_id && $order->route === 'original')
    <button class="btn btn-outline-success w-100 mb-2" onclick="sendToChat(this)">トークに「贈りました」と送る（liff.sendMessages）</button>
  @endif
  <a class="btn btn-success w-100" href="{{ route('liff.history') }}">送信履歴を見る</a>
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
</script>
@endpush
