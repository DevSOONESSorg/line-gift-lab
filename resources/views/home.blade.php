@extends('layouts.lab')
@section('title', 'トップ')
@section('content')
  <h1 class="h3">{{ config('app.name') }}（教材）へようこそ</h1>
  <p class="text-muted">LINEでお店にギフトを贈るサービスのしくみを、手を動かしながら学ぶ教材です。Laravel で作られています。</p>

  <h2 class="h5 mt-4">だれが・何をするか</h2>
  <table class="table table-bordered bg-white">
    <thead class="table-light"><tr><th style="width:160px">人</th><th>すること</th></tr></thead>
    <tbody>
      <tr><td><strong>お客さん</strong></td><td>共通アプリ（運営の公式LINE）でお店をさがすか、お店の公式LINE・QRから、商品を選んで<strong>お店にギフトを贈る</strong>。カードは初回だけ登録（銀行振込も選べる）。お店からお礼動画が届く</td></tr>
      <tr><td><strong>お店のオーナー</strong></td><td>運営の公式LINEから<strong>出店登録</strong>（店舗情報・代表者・振込先）と<strong>商品登録</strong>。ギフトが届いたら「受け取る」→ お礼動画を送る</td></tr>
      <tr><td><strong>運営スタッフ</strong></td><td><strong>管理画面</strong>で承認・手数料・代理店・注文・売上の管理。オリジナル（お店の公式LINE）を希望したお店の<strong>LINE構築</strong>。スマホは動作確認だけ</td></tr>
    </tbody>
  </table>

  <h2 class="h5 mt-4">裏側の3つのシステム</h2>
  <div class="row g-3">
    <div class="col-md"><div class="card h-100 border-top border-4 border-warning"><div class="card-body">
      <h3 class="h6">スマホ <small class="text-muted">お客さん・オーナー</small></h3>
      <p class="small">LINEアプリで友だち追加し、トークやリッチメニューから操作する。</p>
      <a href="{{ route('mock.phone') }}">疑似スマホを開く →</a></div></div></div>
    <div class="col-md"><div class="card h-100 border-top border-4 border-success"><div class="card-body">
      <h3 class="h6">LINE社 <small class="text-muted">疑似LINE</small></h3>
      <p class="small">公式アカウント・チャネル・LIFF・リッチメニューを持っている。本物では中身は見えない。</p>
      <a href="{{ route('mock.manager') }}">Manager</a> ／ <a href="{{ route('mock.developers') }}">Developers Console</a></div></div></div>
    <div class="col-md"><div class="card h-100 border-top border-4" style="border-color:#6b46c1!important"><div class="card-body">
      <h3 class="h6">自社サーバー <small class="text-muted">おくりギフト（Laravel）</small></h3>
      <p class="small">店舗・商品・注文・お客さんを持っている。LINEの設定値の「コピー」を持ち、LINE社とやりとりする。</p>
      <a href="{{ route('admin.dashboard') }}">管理画面を開く →</a></div></div></div>
  </div>
  <p class="text-center mt-3"><a class="btn btn-dark" href="{{ route('inside') }}">裏側ビュー</a>　3者の間で何が起きたかが、時間順に全部見えます</p>

  <h2 class="h5 mt-4">まずやること（コースA 第0章）</h2>
  <ol>
    <li><a href="{{ route('mock.phone') }}">疑似スマホ</a>で「おくりギフト(dev)」→ メニュー「お店をさがす」→「見本バー」にギフトを贈る</li>
    <li>「シャンパンバー ルミエール」の公式LINE → メニュー「ギフトを贈る」で贈る（オリジナルの入口）</li>
    <li><a href="{{ route('inside') }}">裏側ビュー</a>で何が起きたかを見る</li>
    <li>管理画面（<code>admin@example.com</code> ／ <code>Taiken-2026</code>）で注文を見る</li>
  </ol>
@endsection
