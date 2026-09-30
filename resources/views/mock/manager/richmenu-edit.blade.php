@extends('mock.manager._layout')
@section('title', 'リッチメニューを編集')
@section('page')
<h1 class="h4">リッチメニューを編集</h1>
<p class="text-muted small">画像と分割はそのままで、<strong>ボタンを押したときの動き（アクション）</strong> だけを変えます。お店がすでに使っているリッチメニューに、ギフトのボタンをつなぐときの操作です。</p>
<form method="post" action="{{ route('mock.manager.oa.richmenus.update', [$oa, $menu]) }}" class="card card-body" style="max-width:860px">@csrf
  <div class="row g-3">
    <div class="col-md-6">
      <div class="rm-edit-preview" style="aspect-ratio:{{ $menu->width }} / {{ $menu->height }}">
        <img src="{{ asset('storage/'.$menu->image_path) }}" alt="">
        @foreach ($menu->areas() as $i => $a)
          <div class="rm-box" style="left:{{ $a['x']*100 }}%;top:{{ $a['y']*100 }}%;width:{{ $a['w']*100 }}%;height:{{ $a['h']*100 }}%"><span>{{ chr(65 + $i) }}</span></div>
        @endforeach
      </div>
      <p class="small text-muted mt-2">{{ config("lab.richmenu_templates.{$menu->template}.label") }} ／ {{ $menu->width }}×{{ $menu->height }}px</p>
      <label class="form-label small">タイトル（管理用）</label><input name="title" class="form-control form-control-sm mb-2" value="{{ $menu->title }}">
      <label class="form-label small">メニューバーのテキスト</label><input name="bar_text" class="form-control form-control-sm" value="{{ $menu->bar_text }}">
    </div>
    <div class="col-md-6">
      <h2 class="h6">アクション</h2>
      @foreach ($menu->areas() as $i => $_)
        @php $act = $menu->actions[$i] ?? ['type' => 'none', 'value' => '']; @endphp
        <div class="rm-act mb-3">
          <div class="mb-1"><strong>{{ chr(65 + $i) }}</strong> @isset($act['label'])<span class="small text-muted">画像の表示：「{{ $act['label'] }}」</span>@endisset</div>
          <div class="d-flex gap-2">
            <select name="action_type_{{ $i }}" class="form-select form-select-sm w-auto">
              @foreach (['link' => 'リンク', 'text' => 'テキスト', 'none' => '設定しない'] as $v => $t)<option value="{{ $v }}" @selected(old("action_type_$i", $act['type']) === $v)>{{ $t }}</option>@endforeach
            </select>
            <input name="action_value_{{ $i }}" class="form-control form-control-sm" value="{{ old("action_value_$i", $act['value']) }}" placeholder="https://liff.line.me/{LIFF ID} または送る文字">
          </div>
        </div>
      @endforeach
    </div>
  </div>
  <div class="d-flex gap-2 mt-2"><button class="btn btn-line px-4">保存</button><a class="btn btn-light" href="{{ route('mock.manager.oa.richmenus', $oa) }}">キャンセル</a></div>
</form>
@endsection
