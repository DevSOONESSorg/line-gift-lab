@extends('layouts.lab')
@section('title', '疑似スマホ')
@section('containerClass', 'phones-page')
@section('content')
<div class="phones">
  @foreach ($src as $phone => $url)
    <section class="phone-col">
      <h2 class="phone-title phone-title-{{ $phone }}">
        <i class="bi {{ $phone === 'customer' ? 'bi-gift' : 'bi-shop' }}"></i>
        {{ \App\Models\Mock\LineUser::PHONES[$phone] }}のスマホ
        <small>{{ $phone === 'customer' ? 'お店にギフトを贈る人' : 'このサービスを導入したお店の人（受け取る・お礼を送る）' }}</small>
      </h2>
      <iframe class="phone-shell" src="{{ $url }}" title="{{ \App\Models\Mock\LineUser::PHONES[$phone] }}のスマホ"></iframe>
    </section>
  @endforeach
</div>

<aside class="phone-help mx-auto">
  <h2 class="h5">疑似スマホ</h2>
  <p>スマホのLINEの代わりです。LINEでこのサービスを使うのは、<strong>お客さん</strong>と<strong>オーナー</strong>の2つの役です。左右のスマホは、別々の人のLINE（ユーザーIDが違う）です。</p>
  <p class="small">構築エンジニア（私たち）も、動作確認では自分のスマホで触ります。そのときは <strong>お客さん役</strong>（贈れるか）や <strong>オーナー役</strong>（受け取れるか）になって確かめます。だれが触っても、サービスから見れば「お客さん」か「オーナー」のどちらかです。</p>
  <ul class="small">
    <li>緑の吹き出し＝そのスマホの人が送ったもの、白＝公式アカウントから届いたもの</li>
    <li>吹き出しの下の小さな文字で「誰が返したか」がわかります（<strong>bot</strong>＝自社サーバー／<strong>応答メッセージ</strong>＝LINE社の定型文）</li>
    <li>リッチメニューにマウスを乗せると、設定されている動作が見えます</li>
    <li>お客さんが贈ると、右のオーナーのスマホに「ギフトが届きました」とお知らせが届きます</li>
  </ul>
  <a href="{{ route('inside') }}" target="_blank">裏側ビューを別タブで開く →</a>
</aside>
@endsection
