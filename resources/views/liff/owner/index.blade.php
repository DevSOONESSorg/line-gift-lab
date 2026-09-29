@extends('layouts.liff')
@section('title', '店舗管理')
@section('content')
  <h1 class="h5 mt-3">店舗管理</h1>
  @forelse ($stores as $s)
    <div class="item">
      <div><strong>{{ $s->name }}</strong> @if (! $s->is_approved)<span class="badge text-bg-warning">承認待ち</span>@endif
        <span class="badge text-bg-light border">{{ $s->is_listed_in_directory ? '共通掲載' : 'オリジナル' }}</span></div>
      <div class="small">商品 {{ $s->menus_count }} 件 ／ 受け取り待ち {{ $s->waiting_count }} 件</div>
      <div class="d-flex gap-2 mt-2"><a class="btn btn-sm btn-success" href="{{ route('liff.manage.gifts', $s) }}">ギフト一覧</a><a class="btn btn-sm btn-outline-success" href="{{ route('liff.manage.menus', $s) }}">商品登録</a></div>
    </div>
  @empty
    <p>あなたがオーナーのお店はまだありません。<a href="{{ route('liff.register') }}">出店登録</a>から登録してください。</p>
  @endforelse
@endsection
