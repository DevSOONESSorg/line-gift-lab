@extends('layouts.lab')
@section('title', '疑似スマホ')
@section('containerClass', 'phone-page')
@section('content')
<div class="phone">
  <div class="phone-status"><span>9:41</span><span>{{ $me->display_name }} のLINE</span><span>●●●</span></div>

  @if ($open)
    {{-- スマホの中のブラウザ（LIFF） --}}
    <div class="browser-bar">
      <a href="{{ route('mock.phone', $chat ? ['chat' => $chat->id] : []) }}">✕ 閉じる</a>
      <span>{{ !empty($open['liffId']) ? 'LIFF '.$open['liffId'] : 'ブラウザ' }}</span>
    </div>
    @if (!empty($open['error']))
      <div class="phone-body p-3"><div class="alert alert-danger">{{ $open['error'] }}</div></div>
    @elseif (!empty($open['external']))
      <div class="phone-body p-3"><p>外部のページです。</p><a href="{{ $open['external'] }}" target="_blank" rel="noopener">{{ $open['external'] }}</a></div>
    @else
      <iframe class="phone-frame" src="{{ $open['iframe'] }}"></iframe>
    @endif

  @elseif ($addOa)
    <div class="chat-head"><a href="{{ route('mock.phone') }}">‹</a><span>友だち追加</span><span></span></div>
    <div class="phone-body p-3 text-center">
      @if (isset($addOa->notFound))
        <div class="alert alert-danger">「{{ $addOa->notFound }}」というIDの公式アカウントは見つかりません。</div>
      @else
        <div class="oa-icon big mx-auto">{{ mb_substr($addOa->name, 0, 1) }}</div>
        <h3 class="h5 mt-2">{{ $addOa->name }}</h3>
        <p class="text-muted">{{ $addOa->basic_id }}</p>
        <form method="post" action="{{ route('mock.phone.add') }}">@csrf<input type="hidden" name="basic_id" value="{{ $addOa->basic_id }}"><button class="btn btn-line px-5">追加</button></form>
      @endif
    </div>

  @elseif ($chat)
    <div class="chat-head">
      <a href="{{ route('mock.phone') }}">‹</a><span>{{ $chat->name }}</span>
      <form method="post" action="{{ route('mock.phone.block', $chat) }}">@csrf
        <input type="hidden" name="blocked" value="{{ $friendRow && $friendRow->blocked ? 0 : 1 }}">
        <button class="btn btn-link btn-sm p-0">{{ $friendRow && $friendRow->blocked ? 'ブロック解除' : 'ブロック' }}</button></form>
    </div>
    <div class="phone-body chat" id="chat">
      @forelse ($messages as $m)
        <div class="msg {{ $m->direction }}">
          <div class="bubble">{{ $m->text }}</div>
          <div class="meta">{{ substr($m->created_at, 11, 5) }} {{ ['bot' => 'bot', 'auto' => '応答メッセージ', 'greeting' => 'あいさつ', 'liff' => 'LIFFから送信', 'user' => ''][$m->via] ?? '' }}</div>
        </div>
      @empty
        <p class="text-center text-white-50">まだメッセージはありません</p>
      @endforelse
    </div>
    @if (! $friendRow)
      <div class="p-3 text-center border-top"><form method="post" action="{{ route('mock.phone.add') }}">@csrf<input type="hidden" name="basic_id" value="{{ $chat->basic_id }}"><button class="btn btn-line">友だち追加</button></form></div>
    @elseif ($friendRow->blocked)
      <div class="p-3 text-center text-muted border-top">ブロック中です</div>
    @else
      @if ($richMenu)
        <div class="richmenu" id="richmenu">
          <img src="{{ asset('storage/'.$richMenu->image_path) }}" alt="">
          @foreach ($richMenu->areas() as $i => $a)
            @php $act = $richMenu->actions[$i] ?? ['type' => 'none']; $style = 'left:'.($a['x']*100).'%;top:'.($a['y']*100).'%;width:'.($a['w']*100).'%;height:'.($a['h']*100).'%'; @endphp
            @if ($act['type'] === 'link')
              <a class="rm-area" style="{{ $style }}" href="{{ route('mock.phone', ['chat' => $chat->id, 'open' => $act['value']]) }}" title="{{ $act['value'] }}"></a>
            @elseif ($act['type'] === 'text')
              <form method="post" action="{{ route('mock.phone.send', $chat) }}" class="rm-area" style="{{ $style }}">@csrf<input type="hidden" name="text" value="{{ $act['value'] }}"><button title="「{{ $act['value'] }}」を送る"></button></form>
            @else
              <span class="rm-area none" style="{{ $style }}"></span>
            @endif
          @endforeach
        </div>
      @endif
      <form class="chat-input" method="post" action="{{ route('mock.phone.send', $chat) }}">@csrf
        @if ($richMenu)<button type="button" class="btn btn-light btn-sm text-nowrap" onclick="document.getElementById('richmenu').classList.toggle('d-none')">≡ {{ $richMenu->bar_text }}</button>@endif
        <input name="text" class="form-control form-control-sm" placeholder="メッセージを入力" autocomplete="off" autofocus>
        <button class="btn btn-line btn-sm text-nowrap">送信</button>
      </form>
    @endif

  @else
    <div class="chat-head"><span></span><span>ホーム</span><span></span></div>
    <div class="phone-body p-3">
      <form method="post" action="{{ route('mock.phone.name') }}" class="d-flex gap-2 align-items-center">@csrf
        <span class="oa-icon">私</span><input name="display_name" class="form-control form-control-sm" value="{{ $me->display_name }}" maxlength="20"><button class="btn btn-sm btn-link text-nowrap">名前を変更</button></form>
      <p class="small text-muted mt-2">あなたのユーザーID：<code class="user-select-all">{{ $me->user_id }}</code></p>
      <h4 class="h6 mt-3">友だち（公式アカウント）</h4>
      @forelse ($friends as $f)
        <a class="friend" href="{{ route('mock.phone', ['chat' => $f->id]) }}"><span class="oa-icon">{{ mb_substr($f->name, 0, 1) }}</span><span>{{ $f->name }}{{ $f->blocked ? '（ブロック中）' : '' }}</span></a>
      @empty
        <p class="text-muted">まだいません</p>
      @endforelse
      <h4 class="h6 mt-3">ID検索で友だち追加</h4>
      <form class="d-flex gap-2"><input name="add" class="form-control form-control-sm" placeholder="@123abcde"><button class="btn btn-sm btn-secondary text-nowrap">検索</button></form>
    </div>
  @endif
</div>

<aside class="phone-help">
  <h2 class="h5">疑似スマホ</h2>
  <p>スマホのLINEの代わりです。LINEでこのサービスを使うのは、次の<strong>2つの役</strong>です。このスマホ1台で、両方になれます。</p>
  <ul class="small">
    <li><strong>お客さん</strong>：お店にギフトを贈る人</li>
    <li><strong>オーナー</strong>：このサービスを導入したお店の人（ギフトを受け取る・お礼を送る）</li>
  </ul>
  <p class="small">構築エンジニア（私たち）も、動作確認では自分のスマホで触ります。そのときは <strong>お客さん役</strong>（贈れるか）や <strong>オーナー役</strong>（受け取れるか）になって確かめます。だれが触っても、サービスから見れば「お客さん」か「オーナー」のどちらかです。</p>
  <ul class="small">
    <li>緑の吹き出し＝あなたが送ったもの、白＝公式アカウントから届いたもの</li>
    <li>吹き出しの下の小さな文字で「誰が返したか」がわかります（<strong>bot</strong>＝自社サーバー／<strong>応答メッセージ</strong>＝LINE社の定型文）</li>
    <li>リッチメニューにマウスを乗せると、設定されている動作が見えます</li>
  </ul>
  <a href="{{ route('inside') }}" target="_blank">裏側ビューを別タブで開く →</a>
</aside>
@endsection
@if ($chat && ! $open)
@push('scripts')
<script>
  const box = document.getElementById('chat');
  if (box) box.scrollTop = box.scrollHeight;
  const last = {{ $lastId }};
  setInterval(async () => {
    const input = document.querySelector('.chat-input input[name=text]');
    if (input && input.value) return;
    const r = await fetch('{{ route('mock.phone.last', $chat) }}').then((x) => x.json()).catch(() => null);
    if (r && r.lastId !== last) location.reload();
  }, 2000);
</script>
@endpush
@endif
