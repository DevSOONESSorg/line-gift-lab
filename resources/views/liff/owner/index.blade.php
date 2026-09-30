@extends('layouts.liff')
@section('theme', 'light')
@section('title', '店舗管理')
@section('content')
  {{-- 本番の「店舗管理」と同じ並び：お店ごとのカード → 贈り物一覧／商品管理／スタッフ管理 --}}
  <div class="l-h">店舗管理</div>
  @forelse ($stores as $s)
    <div class="own-card">
      <div class="own-head">
        <div>
          <div class="own-name">{{ $s->name }}</div>
          @if ($s->is_approved)<span class="own-badge ok">承認済み</span>@else<span class="own-badge wait">承認待ち</span>@endif
        </div>
        <div class="own-icons">
          <button type="button" class="own-icon" onclick="ownNote(this)" aria-label="編集">✏️</button>
          <button type="button" class="own-icon" onclick="ownNote(this)" aria-label="削除">🗑️</button>
        </div>
      </div>
      <a class="own-main" href="{{ route('liff.manage.gifts', $s) }}">贈り物一覧@if ($s->waiting_count)<span class="own-count">{{ $s->waiting_count }}</span>@endif</a>
      <div class="own-sub">
        <a href="{{ route('liff.manage.menus', $s) }}">商品管理</a>
        <button type="button" onclick="ownNote(this)">スタッフ管理</button>
      </div>
      <div class="own-note" hidden>この教材では使えません（本番にはあります）</div>
    </div>
  @empty
    <p>あなたがオーナーのお店はまだありません。<a href="{{ route('liff.register') }}">店舗登録</a>から登録してください。</p>
  @endforelse
  <script>
    function ownNote(el) { const n = el.closest('.own-card').querySelector('.own-note'); n.hidden = false; setTimeout(() => { n.hidden = true; }, 2500); }
  </script>
@endsection
