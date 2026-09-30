@extends('layouts.liff')
@section('theme', 'light')
@section('title', '贈り物一覧')
@section('content')
  @include('liff.owner._nav')
  @forelse ($orders as $o)
    <div class="l-card">
      <div class="d-flex justify-content-between align-items-start"><div class="name">{{ $o->menu_name }}</div>@include('liff._pill', ['order' => $o, 'owner' => true])</div>
      <div class="meta mb-2">{{ $o->created_at->format('Y-m-d H:i') }} ・ ¥{{ number_format($o->amount) }}</div>
      <div class="kv"><span>送り主</span><span>{{ $o->senderLabel() }}</span></div>
      <div class="kv"><span>受取人</span><span>{{ $o->recipientLabel() }}</span></div>
      @if ($o->message)<div class="msgbox"><small>メッセージ</small>{{ $o->message }}</div>@endif
      @if ($o->status === \App\Enums\OrderStatus::Requested)
        <a class="btn-gold mt-3" href="{{ route('liff.manage.gift', [$store, $o]) }}">贈り物を受け取る</a>
      @elseif ($o->status === \App\Enums\OrderStatus::Received)
        <a class="btn-gold mt-3" href="{{ route('liff.manage.thank', [$store, $o]) }}">お礼を送る</a>
      @elseif ($o->status === \App\Enums\OrderStatus::AwaitingPayment)
        <div class="meta mt-2">お客さんの入金を待っています（運営が入金を確認すると、受け取れるようになります）</div>
      @endif
    </div>
  @empty
    <p class="l-empty">まだ贈り物は届いていません。</p>
  @endforelse
@endsection
