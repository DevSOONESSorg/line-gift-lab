{{-- 共通アプリ（お客さん向け）のタブ --}}
<nav class="appnav">
  <a class="{{ request()->routeIs('liff.shops') ? 'on' : '' }}" href="{{ route('liff.shops') }}"><i class="bi bi-search"></i>お店をさがす</a>
  <a class="{{ request()->routeIs('liff.history') ? 'on' : '' }}" href="{{ route('liff.history') }}"><i class="bi bi-clock-history"></i>送信履歴</a>
  <a class="{{ request()->routeIs('liff.thanks') ? 'on' : '' }}" href="{{ route('liff.thanks') }}"><i class="bi bi-camera-video"></i>お礼一覧</a>
</nav>
