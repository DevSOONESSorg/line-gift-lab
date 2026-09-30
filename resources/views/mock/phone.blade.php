@extends('layouts.phone')
@section('title', $me->phoneLabel().'のスマホ')
@section('content')
@php
  // トークの文章の中の URL を押せるようにする（押すと、このスマホの中のブラウザで開く）
  $linkify = fn (string $text) => preg_replace_callback('~https?://[^\s<]+~', fn ($m) => '<a href="'.e(route('mock.phone.screen', ['phone' => $phone, 'chat' => $chat?->id, 'open' => html_entity_decode($m[0])])).'">'.$m[0].'</a>', e($text));
  $closeUrl = route('mock.phone.screen', ['phone' => $phone] + ($chat ? ['chat' => $chat->id] : []));
@endphp
<div class="phone">
  <div class="phone-status"><span>9:41</span><span>{{ $me->display_name }}（{{ $me->phoneLabel() }}）</span><span><i class="bi bi-reception-4"></i> <i class="bi bi-wifi"></i> <i class="bi bi-battery-full"></i></span></div>
  <div class="phone-main">

  @if ($addOa)
    <div class="chat-head"><a href="{{ route('mock.phone.screen', $phone) }}"><i class="bi bi-chevron-left"></i></a><span class="title">友だち追加</span><span></span></div>
    <div class="phone-body p-3 text-center">
      @if (isset($addOa->notFound))
        <div class="alert alert-danger">「{{ $addOa->notFound }}」というIDの公式アカウントは見つかりません。</div>
      @else
        <div class="oa-icon big mx-auto mt-4">{{ mb_substr($addOa->name, 0, 1) }}</div>
        <h3 class="h5 mt-2">{{ $addOa->name }}</h3>
        <p class="text-muted">{{ $addOa->basic_id }}</p>
        <form method="post" action="{{ route('mock.phone.add', $phone) }}">@csrf<input type="hidden" name="basic_id" value="{{ $addOa->basic_id }}"><button class="btn btn-line px-5">追加</button></form>
      @endif
    </div>

  @elseif ($chat)
    <div class="chat-head">
      <a href="{{ route('mock.phone.screen', $phone) }}"><i class="bi bi-chevron-left"></i></a>
      <span class="title">{{ $chat->name }}</span>
      <span class="icons">
        <i class="bi bi-search"></i>
        <details class="chat-menu"><summary><i class="bi bi-list"></i></summary>
          <form method="post" action="{{ route('mock.phone.block', [$phone, $chat]) }}">@csrf
            <input type="hidden" name="blocked" value="{{ $friendRow && $friendRow->blocked ? 0 : 1 }}">
            <button>{{ $friendRow && $friendRow->blocked ? 'ブロック解除' : 'ブロック' }}</button></form>
        </details>
      </span>
    </div>
    <div class="phone-body chat" id="chat">
      @php $lastDay = null; @endphp
      @forelse ($messages as $m)
        @php $day = substr($m->created_at, 0, 10); @endphp
        @if ($day !== $lastDay)<div class="day"><span>{{ $day === now()->toDateString() ? '今日' : \Illuminate\Support\Carbon::parse($day)->locale('ja')->isoFormat('M/D（ddd）') }}</span></div>@php $lastDay = $day; @endphp@endif
        @if ($m->direction === 'in')
          <div class="msg in"><div class="side"><span class="read">既読</span>{{ substr($m->created_at, 11, 5) }}</div><div class="bubble">{!! $linkify($m->text) !!}</div></div>
        @else
          <div class="msg out">
            @include('mock._avatar', ['oa' => $chat, 'size' => 'xs'])
            <div class="bubble">{!! $linkify($m->text) !!}</div>
            <div class="side">{{ substr($m->created_at, 11, 5) }}<span class="via">{{ ['bot' => 'bot', 'auto' => '応答メッセージ', 'greeting' => 'あいさつ', 'liff' => 'LIFF'][$m->via] ?? '' }}</span></div>
          </div>
        @endif
      @empty
        <p class="text-center text-white-50 mt-3">まだメッセージはありません</p>
      @endforelse
    </div>
    @if (! $friendRow)
      <div class="p-3 text-center border-top"><form method="post" action="{{ route('mock.phone.add', $phone) }}">@csrf<input type="hidden" name="basic_id" value="{{ $chat->basic_id }}"><button class="btn btn-line">友だち追加</button></form></div>
    @elseif ($friendRow->blocked)
      <div class="p-3 text-center text-muted border-top">ブロック中です</div>
    @else
      @if ($richMenu)
        <div class="richmenu" id="richmenu">
          <img src="{{ asset('storage/'.$richMenu->image_path) }}" alt="">
          @foreach ($richMenu->areas() as $i => $a)
            @php $act = $richMenu->actions[$i] ?? ['type' => 'none']; $style = 'left:'.($a['x']*100).'%;top:'.($a['y']*100).'%;width:'.($a['w']*100).'%;height:'.($a['h']*100).'%'; @endphp
            @if ($rmLocked)
              <span class="rm-area none" style="{{ $style }}" title="構築ナビの「リッチメニュー」の手順が終わると押せます"></span>
            @elseif ($act['type'] === 'link')
              <a class="rm-area" style="{{ $style }}" href="{{ route('mock.phone.screen', ['phone' => $phone, 'chat' => $chat->id, 'open' => $act['value']]) }}" title="{{ $act['value'] }}"></a>
            @elseif ($act['type'] === 'text')
              <form method="post" action="{{ route('mock.phone.send', [$phone, $chat]) }}" class="rm-area" style="{{ $style }}">@csrf<input type="hidden" name="text" value="{{ $act['value'] }}"><button title="「{{ $act['value'] }}」を送る"></button></form>
            @else
              <span class="rm-area none" style="{{ $style }}"></span>
            @endif
          @endforeach
        </div>
        <div class="menu-bar" id="menubar">
          <button type="button" class="kbd" onclick="showInput(true)" title="キーボード"><i class="bi bi-keyboard"></i></button>
          <button type="button" class="label" onclick="document.getElementById('richmenu').classList.toggle('d-none')">{{ $richMenu->bar_text }} <i class="bi bi-caret-down-fill"></i></button>
          <span></span>
        </div>
      @endif
      <form class="chat-input {{ $richMenu ? 'd-none' : '' }}" id="chatinput" method="post" action="{{ route('mock.phone.send', [$phone, $chat]) }}">@csrf
        @if ($richMenu)<button type="button" class="btn btn-light btn-sm" onclick="showInput(false)" title="メニューに戻る"><i class="bi bi-grid-3x2-gap"></i></button>@endif
        <input name="text" class="form-control form-control-sm" placeholder="メッセージを入力" autocomplete="off">
        <button class="btn btn-line btn-sm text-nowrap">送信</button>
      </form>
    @endif

  @elseif (request('tab') === 'friends')
    {{-- 友だちタブ：自分のプロフィール（表示名・ユーザーID）と、友だちの公式アカウント --}}
    <div class="line-top"><div class="tabs"><a href="{{ route('mock.phone.screen', $phone) }}">トーク</a><b>友だち</b></div></div>
    <div class="phone-body px-3">
      <div class="me-card">
        <span class="av av-me">{{ mb_substr($me->display_name, 0, 1) }}</span>
        <form method="post" action="{{ route('mock.phone.name', $phone) }}" class="flex-grow-1">@csrf
          <div class="d-flex gap-1"><input name="display_name" class="form-control form-control-sm fw-bold" value="{{ $me->display_name }}" maxlength="20"><button class="btn btn-sm btn-light text-nowrap">変更</button></div>
          <div class="uid">ユーザーID <code class="user-select-all">{{ $me->user_id }}</code></div>
        </form>
      </div>
      <p class="small text-muted mb-1">↑ LINEの表示名（あいさつメッセージの {Nickname} に入る）と、公式アカウントごとに決まるユーザーID</p>
      <h4 class="list-h">公式アカウント {{ $friends->count() }}</h4>
      @foreach ($friends as $f)
        <a class="talk" href="{{ route('mock.phone.screen', ['phone' => $phone, 'chat' => $f->id]) }}">@include('mock._avatar', ['oa' => $f, 'size' => 'sm'])<span class="t"><b>{{ $f->name }}</b>@if ($f->blocked)<span class="p">ブロック中</span>@endif</span></a>
      @endforeach
    </div>
    @include('mock._tabbar', ['active' => 'home'])

  @else
    {{-- トーク一覧（LINEアプリを開いたときの画面） --}}
    <div class="line-top">
      <div class="tabs"><b>トーク <i class="bi bi-caret-down-fill"></i></b><a href="{{ route('mock.phone.screen', ['phone' => $phone, 'tab' => 'friends']) }}">友だち</a></div>
      <div class="icons"><i class="bi bi-emoji-smile"></i><i class="bi bi-calendar3"></i><i class="bi bi-plus-lg"></i></div>
    </div>
    <form class="line-search" action="{{ route('mock.phone.screen', $phone) }}"><i class="bi bi-search"></i><input name="add" placeholder="検索（@ID で公式アカウントを友だち追加）" autocomplete="off"><i class="bi bi-qr-code-scan"></i></form>
    <div class="phone-body">
      @forelse ($talks as $f)
        <a class="talk" href="{{ route('mock.phone.screen', ['phone' => $phone, 'chat' => $f->id]) }}">
          @include('mock._avatar', ['oa' => $f])
          <span class="t">
            <b class="{{ $f->is_platform ? 'official' : '' }}">{{ $f->name }}</b>
            <span class="p">{{ $f->blocked ? 'ブロック中' : \Illuminate\Support\Str::limit(preg_replace('/\s+/u', ' ', $f->last?->text ?? ''), 60) }}</span>
          </span>
          <span class="d">{{ $f->last ? (substr($f->last->created_at, 0, 10) === now()->toDateString() ? substr($f->last->created_at, 11, 5) : \Illuminate\Support\Carbon::parse($f->last->created_at)->format('n/j')) : '' }}</span>
        </a>
      @empty
        <p class="text-muted text-center mt-5 small">トークはまだありません。<br>上の検索に @ID を入れて、公式アカウントを友だち追加しましょう。</p>
      @endforelse
    </div>
    @include('mock._tabbar', ['active' => 'talk'])
  @endif

  @if ($open)
    {{-- スマホの中のブラウザ（LIFF）。サイズが Full なら全画面、Tall / Compact ならトークの上に下から出る --}}
    <div class="liff-layer size-{{ strtolower($open['size'] ?? 'Full') }}">
      <div class="liff-sheet">
        <div class="browser-bar">
          <a href="javascript:void(0)" onclick="history.back()" title="戻る"><i class="bi bi-chevron-left"></i></a>
          <div class="t"><b>{{ $open['liffName'] ?? 'ブラウザ' }}</b><small>{{ $open['host'] ?? '' }}{{ !empty($open['liffId']) ? '　LIFF '.$open['liffId'].'・'.$open['size'] : '' }}</small></div>
          <a class="liff-close" href="{{ $closeUrl }}" title="閉じる"><i class="bi bi-x-lg"></i></a>
        </div>
        @if (!empty($open['error']))
          <div class="phone-body p-3"><div class="alert alert-danger" style="white-space:pre-line">{{ $open['error'] }}</div></div>
        @elseif (!empty($open['external']))
          <div class="phone-body p-3"><p>外部のページです。</p><a href="{{ $open['external'] }}" target="_blank" rel="noopener">{{ $open['external'] }}</a></div>
        @else
          <iframe class="phone-frame" src="{{ $open['iframe'] }}"></iframe>
        @endif
      </div>
    </div>
  @endif
  </div>
</div>
@endsection
@push('scripts')
<script>
  function showInput(on) {
    document.getElementById('chatinput')?.classList.toggle('d-none', !on);
    document.getElementById('menubar')?.classList.toggle('d-none', on);
    document.getElementById('richmenu')?.classList.toggle('d-none', on);
    if (on) document.querySelector('#chatinput input[name=text]')?.focus();
  }
</script>
@if ($chat && ! $open)
<script>
  const box = document.getElementById('chat');
  if (box) box.scrollTop = box.scrollHeight;
  const last = {{ $lastId }};
  setInterval(async () => {
    const input = document.querySelector('#chatinput input[name=text]');
    if (input && input.value) return;
    const r = await fetch('{{ route('mock.phone.last', [$phone, $chat]) }}').then((x) => x.json()).catch(() => null);
    if (r && r.lastId !== last) location.reload();
  }, 2000);
</script>
@endif
@endpush
