@extends('layouts.liff')
@section('title', '贈った履歴')
@section('content')
  <div class="l-title">贈った履歴</div>
  <p class="l-sub">あなたが贈ったギフト</p>
  @forelse ($orders as $o)
    <div class="l-card">
      <div class="d-flex justify-content-between align-items-start"><div class="name">{{ $o->menu_name }}</div>@include('liff._pill', ['order' => $o])</div>
      <div class="price">¥{{ number_format($o->amount) }}</div>
      <div class="kv"><span>お店</span><span>{{ $o->store->name }}</span></div>
      <div class="kv"><span>受取人</span><span>{{ $o->recipientLabel() }}</span></div>
      <div class="kv"><span>日時</span><span>{{ $o->created_at->format('Y/m/d H:i') }}</span></div>
      <div class="kv"><span>注文番号</span><span>{{ $o->order_code }}</span></div>
      @if (in_array($o->status->value, \App\Enums\OrderStatus::settled(), true))
        <a class="small d-inline-block mt-2" href="{{ route('liff.receipt', $o) }}"><i class="bi bi-receipt"></i> 領収書をダウンロード</a>
      @endif
    </div>
  @empty
    <p class="l-empty">まだ贈ったギフトはありません。</p>
  @endforelse
  @include('liff._tabbar')
@endsection
