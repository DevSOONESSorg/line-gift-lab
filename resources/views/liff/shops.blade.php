@extends('layouts.liff')
@section('title', 'お店をさがす')
@section('content')
  @include('liff._appnav')
  <form class="d-flex gap-2 my-3"><input name="q" class="form-control" placeholder="お店の名前" value="{{ request('q') }}"><button class="btn btn-success">検索</button></form>
  @forelse ($stores as $s)
    <a class="shop" href="{{ route('liff.store', [$s->slug, 'via' => 'app']) }}">
      <span class="shop-icon" style="background:{{ $s->image_color }}">{{ mb_substr($s->name, 0, 1) }}</span>
      <span><strong>{{ $s->name }}</strong><br><span class="small text-muted">{{ $s->prefecture }}{{ $s->city }} ・ {{ $s->menus_count }}商品{{ $s->menus_min_price ? ' ・ ¥'.number_format($s->menus_min_price).'〜' : '' }}</span></span>
    </a>
  @empty
    <p class="text-muted">見つかりませんでした。</p>
  @endforelse
@endsection
