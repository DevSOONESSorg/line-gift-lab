@extends('layouts.liff')
@section('title', '贈り物を受け取る')
@section('content')
  @if ($order->status === \App\Enums\OrderStatus::Requested)
    <div class="l-title">贈り物を受け取る</div>
    <p class="l-sub">以下の贈り物が届いています</p>
    <div class="l-card text-center">
      <div class="name" style="color:var(--gold)">{{ $order->menu_name }}</div>
      <div class="fw-bold fs-3 my-2" style="color:var(--price)">¥{{ number_format($order->amount) }}</div>
      <hr style="border-color:var(--line)">
      <div class="text-start">
        <div class="kv"><span>送り主</span><span>{{ $order->senderLabel() }}</span></div>
        <div class="kv"><span>受取人</span><span>{{ $order->recipientLabel() }}</span></div>
        @if ($order->message)<div class="msgbox"><small>メッセージ</small>{{ $order->message }}</div>@endif
      </div>
    </div>
    <form method="post" action="{{ route('liff.manage.receive', [$store, $order]) }}">@csrf<button class="btn-gold">この贈り物を受け取る</button></form>
    <p class="l-note">受け取ると、送り主に通知が届きます<br>{{ $order->expires_at?->format('m/d H:i') }} までに受け取らないと期限切れになります</p>
  @elseif (in_array($order->status, [\App\Enums\OrderStatus::Received, \App\Enums\OrderStatus::Thanked], true))
    <div class="gift-emoji">🎁</div>
    <div class="l-title">贈り物を受け取りました！</div>
    <p class="text-center" style="color:var(--muted)">{{ $order->senderLabel() }}さんからの贈り物</p>
    <p class="text-center fw-bold fs-5">{{ $order->menu_name }}</p>
    <div class="l-card">
      <div class="kv"><span>送り主</span><span>{{ $order->senderLabel() }}</span></div>
      <div class="kv"><span>受取人</span><span>{{ $order->recipientLabel() }}</span></div>
    </div>
    @if ($order->status === \App\Enums\OrderStatus::Received)<a class="btn-gold" href="{{ route('liff.manage.thank', [$store, $order]) }}">お礼を送る</a>@endif
    <a class="btn-ghost" href="{{ route('liff.manage.gifts', $store) }}">贈り物一覧に戻る</a>
  @else
    <div class="l-title">{{ $order->menu_name }}</div>
    <p class="text-center" style="color:var(--muted)">この贈り物は「{{ $order->status->label() }}」です。</p>
    <a class="btn-ghost" href="{{ route('liff.manage.gifts', $store) }}">贈り物一覧に戻る</a>
  @endif
@endsection
