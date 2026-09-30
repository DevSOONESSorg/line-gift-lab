@extends('layouts.liff')
@section('title', 'お礼')
@section('content')
  <div class="l-title">お礼</div>
  <p class="l-sub">お店から届いたお礼の動画・メッセージ</p>
  @forelse ($orders as $o)
    <div class="l-card">
      <div class="d-flex justify-content-between"><span class="name" style="color:var(--gold)">{{ $o->store->name }}</span><span class="meta">{{ $o->thanked_at->format('Y/m/d') }}</span></div>
      <div class="meta mb-2">「{{ $o->menu_name }}」へのお礼</div>
      @if ($o->video_url)<video src="{{ $o->video_url }}" controls playsinline class="w-100 rounded"></video>@endif
      @if ($o->thank_message)<div class="msgbox">{{ $o->thank_message }}</div>@endif
    </div>
  @empty
    <p class="l-empty">まだお礼は届いていません。</p>
  @endforelse
  @include('liff._tabbar')
@endsection
