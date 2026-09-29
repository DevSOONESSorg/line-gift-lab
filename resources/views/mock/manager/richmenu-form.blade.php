@extends('mock.manager._layout')
@section('title', 'リッチメニューを作成')
@section('page')
<h1 class="h4">リッチメニューを作成</h1>
<form method="post" action="{{ route('mock.manager.oa.richmenus.store', $oa) }}" enctype="multipart/form-data" class="card card-body" style="max-width:760px">@csrf
  <h2 class="h6">表示設定</h2>
  <label class="form-label">タイトル（管理用・ユーザーには見えない）</label><input name="title" class="form-control mb-2" value="{{ old('title') }}">
  <label class="form-label">表示期間</label><input class="form-control mb-2" value="今日 〜 1年後（体験では固定）" disabled>
  <label class="form-label">メニューバーのテキスト</label><input name="bar_text" class="form-control mb-2" value="{{ old('bar_text', 'メニュー') }}">
  <div class="form-check mb-3"><input class="form-check-input" type="checkbox" name="is_default" value="1" id="d" checked><label class="form-check-label" for="d">メニューのデフォルト表示：表示する（トークを開いたときに出る）</label></div>
  <h2 class="h6">コンテンツ設定</h2>
  <label class="form-label">テンプレート</label>
  <select name="template" id="tpl" class="form-select mb-1"><option value="">選択</option>
    @foreach (config('lab.richmenu_templates') as $k => $t)<option value="{{ $k }}" @selected(old('template') === $k)>{{ $t['label'] }}（{{ collect(config("lab.richmenu_sizes.{$t['size']}"))->map(fn ($s) => implode('×', $s))->join(' / ') }}）</option>@endforeach
  </select>
  <p class="small text-muted">枠つきのテンプレート画像は <a href="/samples/templates/" target="_blank">こちら</a>。まずは「小・1分割」で1ボタンから。</p>
  <label class="form-label">画像（PNG / JPEG・1MBまで）</label><input type="file" name="image" accept="image/png,image/jpeg" class="form-control mb-2">
  <div id="preview" class="tpl-preview"></div>
  <h2 class="h6 mt-2">アクション</h2>
  <div id="actions"></div>
  <button class="btn btn-success mt-2 align-self-start">保存</button>
</form>
@endsection
@push('scripts')
<script>
  const templates = @json(collect(config('lab.richmenu_templates'))->map(fn ($t, $k) => ['size' => $t['size'], 'areas' => \App\Models\Mock\RichMenu::templateAreas($k)]));
  const old = @json(old());
  function render() {
    const t = templates[document.getElementById('tpl').value];
    const box = document.getElementById('actions'), pv = document.getElementById('preview');
    box.innerHTML = ''; pv.innerHTML = '';
    if (!t) return;
    pv.style.aspectRatio = t.size === 'large' ? '2500 / 1686' : '2500 / 843';
    t.areas.forEach((a, i) => {
      const L = String.fromCharCode(65 + i), type = old['action_type_' + i] || 'link', val = (old['action_value_' + i] || '').replace(/"/g, '&quot;');
      pv.insertAdjacentHTML('beforeend', `<div style="left:${a.x*100}%;top:${a.y*100}%;width:${a.w*100}%;height:${a.h*100}%">${L}</div>`);
      box.insertAdjacentHTML('beforeend', `<div class="d-flex gap-2 mb-2 align-items-center"><strong>${L}</strong>
        <select name="action_type_${i}" class="form-select form-select-sm w-auto"><option value="link" ${type==='link'?'selected':''}>リンク</option><option value="text" ${type==='text'?'selected':''}>テキスト</option><option value="none" ${type==='none'?'selected':''}>設定しない</option></select>
        <input name="action_value_${i}" value="${val}" class="form-control form-control-sm" placeholder="https://liff.line.me/{LIFF_ID} または送る文字"></div>`);
    });
  }
  document.getElementById('tpl').addEventListener('change', render); render();
</script>
@endpush
