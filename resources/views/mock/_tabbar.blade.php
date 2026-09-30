{{-- LINEアプリの下のタブ（体験で使うのは ホーム＝友だち と トーク だけ） --}}
<nav class="line-tabbar">
  <a class="{{ $active === 'home' ? 'on' : '' }}" href="{{ route('mock.phone.screen', ['phone' => $phone, 'tab' => 'friends']) }}"><i class="bi bi-house"></i>ホーム</a>
  <a class="{{ $active === 'talk' ? 'on' : '' }}" href="{{ route('mock.phone.screen', $phone) }}"><i class="bi bi-chat-fill"></i>トーク</a>
  <span><i class="bi bi-bag"></i>ショッピング</span>
  <span><i class="bi bi-newspaper"></i>ニュース</span>
  <span><i class="bi bi-grid"></i>アプリ</span>
</nav>
