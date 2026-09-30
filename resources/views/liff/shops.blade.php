@extends('layouts.liff')
@section('title', 'お店をさがす')
@section('content')
  <div class="l-title">お店をさがす</div>
  <p class="l-sub">ギフトを贈りたいお店を選んでください</p>
  <form class="d-flex gap-2 mb-4"><input name="q" class="l-input" placeholder="お店の名前" value="{{ request('q') }}"><button class="btn-gold sm text-nowrap">検索</button></form>
  @forelse ($stores as $s)
    <a class="l-card shop" href="{{ route('liff.store', [$s->slug, 'via' => 'app']) }}">
      <span class="shop-icon" style="background:{{ $s->image_color }}">{{ mb_substr($s->name, 0, 1) }}</span>
      <span><span class="name" style="color:var(--gold)">{{ $s->name }}</span><br><span class="meta">{{ $s->prefecture }}{{ $s->city }} ・ {{ $s->menus_count }}商品{{ $s->menus_min_price ? ' ・ ¥'.number_format($s->menus_min_price).'〜' : '' }}</span></span>
    </a>
  @empty
    <p class="l-empty">見つかりませんでした。</p>
  @endforelse
  @include('liff._tabbar')
@endsection
