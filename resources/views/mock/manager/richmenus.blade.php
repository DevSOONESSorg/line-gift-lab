@extends('mock.manager._layout')
@section('title', 'リッチメニュー')
@section('page')
<div class="d-flex align-items-center mb-3"><h1 class="h4 mb-0">リッチメニュー</h1><a class="btn btn-sm btn-outline-secondary ms-auto" href="{{ route('mock.manager.oa.richmenus.create', $oa) }}">作成</a></div>
@forelse ($menus as $m)
  <div class="card card-body mb-3 d-flex flex-row gap-3 flex-wrap">
    <img src="{{ asset('storage/'.$m->image_path) }}" style="width:260px;border:1px solid #ddd" alt="">
    <div>
      <h3 class="h6">{{ $m->title }} @if ($m->is_default)<span class="badge text-bg-success">表示中</span>@endif</h3>
      <p class="small text-muted">{{ config("lab.richmenu_templates.{$m->template}.label") }} ／ {{ $m->width }}×{{ $m->height }}px</p>
      <ul class="small">@foreach ($m->actions as $i => $a)<li>{{ chr(65 + $i) }}{{ isset($a['label']) ? '（'.$a['label'].'）' : '' }}：{{ ['link' => 'リンク '.$a['value'], 'text' => 'テキスト「'.$a['value'].'」'][$a['type']] ?? '設定なし' }}</li>@endforeach</ul>
      <div class="d-flex gap-2">
        <a class="btn btn-sm btn-outline-primary" href="{{ route('mock.manager.oa.richmenus.edit', [$oa, $m]) }}">編集</a>
        @unless ($m->is_default)<form method="post" action="{{ route('mock.manager.oa.richmenus.default', [$oa, $m]) }}">@csrf<button class="btn btn-sm btn-outline-success">これを表示する</button></form>@endunless
        <form method="post" action="{{ route('mock.manager.oa.richmenus.destroy', [$oa, $m]) }}" onsubmit="return confirm('削除しますか？')">@csrf @method('DELETE')<button class="btn btn-sm btn-outline-danger">削除</button></form>
      </div>
    </div>
  </div>
@empty
  <p class="text-muted">リッチメニューはまだありません。</p>
@endforelse
@endsection
