@extends('layouts.liff')
@section('title', 'お礼を送る')
@section('content')
  <div class="l-title">お礼を送る</div>
  <p class="l-sub">{{ $order->senderLabel() }}さんへ（{{ $order->menu_name }}）</p>
  <form method="post" action="{{ route('liff.manage.thank.post', [$store, $order]) }}" enctype="multipart/form-data">
    @csrf
    <div class="l-label">お礼動画（mp4 / mov・20MBまで）</div>
    <input type="file" name="video" accept="video/*" class="form-control">
    <p class="small mt-2" style="color:var(--muted)">スマホで撮った短い動画でOK。動画がない場合はメッセージだけでも送れます（体験用）。</p>
    <div class="l-label">メッセージ</div>
    <textarea name="thank_message" class="l-textarea mb-4" placeholder="例：ありがとうございます！みんなでいただきました！">{{ old('thank_message') }}</textarea>
    <button class="btn-gold">お礼を送る</button>
  </form>
  <a class="btn-ghost" href="{{ route('liff.manage.gifts', $store) }}">贈り物一覧に戻る</a>
@endsection
