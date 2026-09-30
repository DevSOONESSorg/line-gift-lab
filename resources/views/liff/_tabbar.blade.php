{{-- お客さん向けの下のタブ。ホームは「開いたお店」か「お店をさがす」 --}}
@php $home = $home ?? route('liff.shops'); @endphp
<nav class="tabbar">
  <a class="{{ request()->routeIs('liff.store', 'liff.shops', 'liff.order.form') ? 'on' : '' }}" href="{{ $home }}"><i class="bi bi-house-door"></i>ホーム</a>
  <a class="{{ request()->routeIs('liff.history') ? 'on' : '' }}" href="{{ route('liff.history') }}"><i class="bi bi-calendar2-week"></i>贈った履歴</a>
  <a class="{{ request()->routeIs('liff.thanks') ? 'on' : '' }}" href="{{ route('liff.thanks') }}"><i class="bi bi-chat-square"></i>お礼</a>
</nav>
