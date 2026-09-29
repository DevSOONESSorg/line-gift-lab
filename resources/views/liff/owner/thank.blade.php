@extends('layouts.liff')
@section('title', 'お礼を送る')
@section('content')
  @include('liff.owner._nav')
  <div class="item"><strong>#{{ $order->id }} {{ $order->menu_name }}</strong><div class="small">{{ $order->customer->line_display_name }} さんへのお礼</div></div>
  <form method="post" action="{{ route('liff.manage.thank.post', [$store, $order]) }}" enctype="multipart/form-data">
    @csrf
    <label class="form-label">お礼動画（mp4 / mov・20MBまで）</label>
    <input type="file" name="video" accept="video/*" class="form-control mb-2">
    <p class="small text-muted">スマホで撮った短い動画でOK。動画がない場合はメッセージだけでも送れます（体験用）。</p>
    <label class="form-label">メッセージ</label>
    <textarea name="thank_message" class="form-control mb-3" rows="2" placeholder="例：ありがとうございます！みんなでいただきました！">{{ old('thank_message') }}</textarea>
    <button class="btn btn-success w-100">お礼を送る</button>
  </form>
@endsection
