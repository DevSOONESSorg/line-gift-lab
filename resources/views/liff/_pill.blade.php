@php
  $s = $order->status;
  [$cls, $label] = match ($s) {
    \App\Enums\OrderStatus::Requested => ['pill-wait', $owner ?? false ? '受取待ち' : 'お店の受取待ち'],
    \App\Enums\OrderStatus::Received => ['pill-thank', 'お礼待ち'],
    \App\Enums\OrderStatus::Thanked => ['pill-done', 'お礼済み'],
    \App\Enums\OrderStatus::AwaitingPayment => ['pill-gray', '入金待ち'],
    \App\Enums\OrderStatus::RefundPending, \App\Enums\OrderStatus::Refunded => ['pill-red', $s->label()],
    default => ['pill-gray', $s->label()],
  };
@endphp
<span class="pill {{ $cls }}">{{ $label }}</span>
