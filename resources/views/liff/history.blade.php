@extends('layouts.liff')
@section('title', '送信履歴')
@section('content')
  @include('liff._appnav')
  <h1 class="h6 my-3">送信履歴</h1>
  @forelse ($orders as $o)
    <div class="item">
      <div class="d-flex justify-content-between"><strong>{{ $o->store->name }}</strong><span class="badge text-bg-{{ $o->status->color() }}">{{ $o->status->label() }}</span></div>
      <div class="small">{{ $o->menu_name }} ¥{{ number_format($o->amount) }} ・ {{ $o->created_at->format('Y/m/d H:i') }}</div>
      @if (in_array($o->status->value, \App\Enums\OrderStatus::settled(), true))
        <a class="small" href="{{ route('liff.receipt', $o) }}"><i class="bi bi-receipt"></i> 領収書をダウンロード</a>
      @endif
    </div>
  @empty
    <p class="text-muted">まだ贈ったギフトはありません。</p>
  @endforelse
@endsection
