@extends('layouts.liff')
@section('theme', 'light')
@section('title', '店舗管理')
@section('content')
  <div class="l-h">店舗管理</div>
  @forelse ($stores as $s)
    <div class="l-card">
      <div class="d-flex justify-content-between align-items-start">
        <div class="name">{{ $s->name }}</div>
        <span>@if (! $s->is_approved)<span class="pill pill-wait">承認待ち</span>@endif <span class="pill pill-gray">{{ $s->is_listed_in_directory ? '共通掲載' : 'オリジナル' }}</span></span>
      </div>
      <div class="meta mt-1">商品 {{ $s->menus_count }} 件 ／ 受取待ち {{ $s->waiting_count }} 件</div>
      <div class="d-flex gap-2 mt-3"><a class="btn-gold" href="{{ route('liff.manage.gifts', $s) }}">贈り物一覧</a><a class="btn-ghost mt-0" href="{{ route('liff.manage.menus', $s) }}">商品登録</a></div>
    </div>
  @empty
    <p>あなたがオーナーのお店はまだありません。<a href="{{ route('liff.register') }}">出店登録</a>から登録してください。</p>
  @endforelse
@endsection
