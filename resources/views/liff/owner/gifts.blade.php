@extends('layouts.liff')
@section('title', 'ギフト一覧')
@section('content')
  @include('liff.owner._nav')
  @forelse ($orders as $o)
    <div class="item">
      <div class="d-flex justify-content-between"><strong>#{{ $o->id }} {{ $o->menu_name }}</strong><span class="badge text-bg-{{ $o->status->color() }}">{{ $o->status->label() }}</span></div>
      <div class="small">¥{{ number_format($o->amount) }} ・ {{ $o->customer->line_display_name }} さんから ・ {{ $o->created_at->format('m/d H:i') }}</div>
      @if ($o->message)<div class="small mt-1">「{{ $o->message }}」</div>@endif
      @if ($o->status === \App\Enums\OrderStatus::Requested)
        <form method="post" action="{{ route('liff.manage.receive', [$store, $o]) }}" class="mt-2">@csrf<button class="btn btn-success w-100">受け取る</button></form>
        <div class="small text-muted mt-1">{{ $o->expires_at?->format('m/d H:i') }} までに受け取らないと期限切れになります</div>
      @elseif ($o->status === \App\Enums\OrderStatus::Received)
        <a class="btn btn-outline-success w-100 mt-2" href="{{ route('liff.manage.thank', [$store, $o]) }}">お礼を送る</a>
      @elseif ($o->status === \App\Enums\OrderStatus::AwaitingPayment)
        <div class="small text-muted mt-1">お客さんの入金を待っています</div>
      @endif
    </div>
  @empty
    <p class="text-muted">まだギフトは届いていません。</p>
  @endforelse
@endsection
