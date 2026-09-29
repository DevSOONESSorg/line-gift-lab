@extends('layouts.liff')
@section('title', 'お礼一覧')
@section('content')
  @include('liff._appnav')
  <h1 class="h6 my-3">お礼一覧</h1>
  @forelse ($orders as $o)
    <div class="item">
      <strong>{{ $o->store->name }}</strong> <span class="small text-muted">{{ $o->thanked_at->format('m/d') }} ・ {{ $o->menu_name }}</span>
      @if ($o->video_url)<video src="{{ $o->video_url }}" controls class="w-100 mt-2 rounded"></video>@endif
      @if ($o->thank_message)<p class="mb-0 mt-1">「{{ $o->thank_message }}」</p>@endif
    </div>
  @empty
    <p class="text-muted">まだお礼は届いていません。</p>
  @endforelse
@endsection
