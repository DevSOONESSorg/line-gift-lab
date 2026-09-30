@extends('layouts.admin')
@section('title', "注文詳細 #{$order->id}")
@section('content')
<p><a href="{{ route('admin.orders.index') }}">← 注文管理</a></p>
<h1 class="h3 mb-3">注文詳細 #{{ $order->id }} <span class="badge text-bg-{{ $order->status->color() }}">{{ $order->status->label() }}</span></h1>
<div class="row g-3">
  <div class="col-lg-7">
    <div class="card mb-3"><div class="card-header">注文情報</div><div class="card-body"><dl class="row mb-0">
      <dt class="col-4">注文ID</dt><dd class="col-8">{{ $order->id }}</dd>
      <dt class="col-4">注文番号</dt><dd class="col-8"><code>{{ $order->order_code }}</code> <small class="text-muted">（お客さんに見せる番号。連番の注文IDは見せない）</small></dd>
      <dt class="col-4">送信者名</dt><dd class="col-8">{{ $order->senderLabel() }}</dd>
      <dt class="col-4">受取人</dt><dd class="col-8">{{ $order->recipientLabel() }}</dd>
      <dt class="col-4">店舗</dt><dd class="col-8"><a href="{{ route('admin.stores.show', $order->store) }}">{{ $order->store->name }}</a></dd>
      <dt class="col-4">どこから</dt><dd class="col-8">{{ $order->route === 'common' ? '共通アプリ（運営の公式LINE）' : 'お店の公式LINE・QR' }}</dd>
      <dt class="col-4">メニュー</dt><dd class="col-8">{{ $order->menu_name }}</dd>
      <dt class="col-4">金額</dt><dd class="col-8">¥{{ number_format($order->amount) }}</dd>
      <dt class="col-4">決済方法</dt><dd class="col-8">{{ $order->payment_method->label() }}{{ $order->card_last4 ? " **** {$order->card_last4}" : '' }}</dd>
      <dt class="col-4">ステータス</dt><dd class="col-8"><span class="badge text-bg-{{ $order->status->color() }}">{{ $order->status->label() }}</span></dd>
      <dt class="col-4">期限</dt><dd class="col-8">{{ $order->expires_at?->format('Y/m/d H:i') ?? '—' }}</dd>
      <dt class="col-4">メッセージ</dt><dd class="col-8" style="white-space:pre-wrap">{{ $order->message ?: '—' }}</dd>
      <dt class="col-4">注文日時</dt><dd class="col-8">{{ $order->created_at->format('Y/m/d H:i') }}</dd>
      <dt class="col-4">受取日時</dt><dd class="col-8">{{ $order->received_at?->format('Y/m/d H:i') ?? '—' }}</dd>
    </dl></div></div>
    <div class="card mb-3"><div class="card-header">お礼動画</div><div class="card-body">
      @if ($videoUrl)
        <video src="{{ $videoUrl }}" controls class="w-100" style="max-height:320px"></video>
        <p class="small text-muted mt-2 mb-0">この動画のURLは「署名付きURL」で、10分で見られなくなります（URLが漏れても、ずっとは見られない）。</p>
      @else <p class="text-muted mb-0">—</p> @endif
      @if ($order->thank_message)<p class="mt-2 mb-0">「{{ $order->thank_message }}」</p>@endif
    </div></div>
    <div class="card"><div class="card-header">状態の移り変わり（order_status_logs）</div>
      <table class="table table-sm mb-0"><thead><tr><th>日時</th><th>前</th><th></th><th>後</th><th>だれが</th><th>メモ</th></tr></thead><tbody>
      @foreach ($order->statusLogs as $l)
        <tr><td class="small">{{ $l->created_at->format('m/d H:i:s') }}</td><td>{{ $l->from ? \App\Enums\OrderStatus::from($l->from)->label() : '—' }}</td><td>→</td>
          <td>{{ \App\Enums\OrderStatus::from($l->to)->label() }}</td><td>{{ ['customer' => 'お客さん', 'owner' => 'オーナー', 'admin' => '運営', 'system' => 'システム'][$l->by] ?? $l->by }}</td><td class="small">{{ $l->note }}</td></tr>
      @endforeach
      </tbody></table>
    </div>
  </div>
  <div class="col-lg-5">
    <div class="card mb-3"><div class="card-header">送り主</div><div class="card-body"><dl class="row mb-0">
      <dt class="col-4">名前</dt><dd class="col-8">{{ $order->customer->name ?: '—' }}</dd>
      <dt class="col-4">LINE表示名</dt><dd class="col-8">{{ $order->customer->line_display_name }}</dd>
      <dt class="col-4">LINE ID</dt><dd class="col-8"><code class="small">{{ $order->customer->line_user_id }}</code></dd>
    </dl></div></div>
    <div class="card mb-3"><div class="card-header">売上情報</div><div class="card-body"><dl class="row mb-0">
      <dt class="col-5">売上</dt><dd class="col-7">¥{{ number_format($order->amount) }}</dd>
      <dt class="col-5">手数料率</dt><dd class="col-7">{{ rtrim(rtrim($order->commission_rate, '0'), '.') }}%（注文時点）</dd>
      <dt class="col-5">手数料</dt><dd class="col-7">¥{{ number_format($order->commission) }}</dd>
      <dt class="col-5">店舗振込額</dt><dd class="col-7">¥{{ number_format($order->payout()) }}</dd>
    </dl></div></div>
    <div class="card border-warning"><div class="card-header bg-warning-subtle">操作（教材の練習用）</div><div class="card-body">
      <p class="small text-muted">実物では、入金確認や返金は決済会社・銀行の処理と連動します。ここでは状態の移り変わりを体験するためにボタンにしています。移れない状態のときは、ボタンを押してもエラーになります。</p>
      <form method="post" action="{{ route('admin.orders.confirm-payment', $order) }}" class="mb-2" data-confirm="入金を確認済みにしますか？">@csrf<button class="btn btn-sm btn-outline-primary w-100" @disabled($order->status !== \App\Enums\OrderStatus::AwaitingPayment)>入金を確認した（入金待ち → リクエスト中）</button></form>
      <form method="post" action="{{ route('admin.orders.refund', $order) }}" class="mb-2" data-confirm="返金処理を始めますか？">@csrf<input name="note" class="form-control form-control-sm mb-1" placeholder="返金の理由（例：店舗都合でご案内不可）"><button class="btn btn-sm btn-outline-danger w-100">返金を受け付ける（→ 返金処理待ち）</button></form>
      <form method="post" action="{{ route('admin.orders.refund-complete', $order) }}" data-confirm="返金済みにしますか？">@csrf<button class="btn btn-sm btn-outline-dark w-100" @disabled($order->status !== \App\Enums\OrderStatus::RefundPending)>返金が完了した（→ 返金済み）</button></form>
    </div></div>
  </div>
</div>
@endsection
