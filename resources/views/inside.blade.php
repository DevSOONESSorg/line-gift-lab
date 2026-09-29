@extends('layouts.lab')
@section('title', '裏側ビュー')
@section('containerClass', 'container-xl py-4')
@section('content')
  <h1 class="h4">裏側ビュー</h1>
  <p class="text-muted">スマホ・LINE社・自社サーバー・管理画面の間で起きたことを、新しい順に表示します（自動で更新）。行をクリックすると中身が開きます。</p>
  <div class="d-flex gap-3 align-items-center mb-2 flex-wrap">
    <label class="form-check"><input type="checkbox" class="form-check-input" id="auto" checked> 自動更新</label>
    <select id="filter" class="form-select form-select-sm w-auto">
      <option value="">すべて</option>
      @foreach ($sides as $k => $v)<option value="{{ $k }}">{{ $v }}</option>@endforeach
      <option value="ng">NGだけ</option>
    </select>
    <form method="post" action="{{ route('inside.clear') }}" onsubmit="return confirm('記録を全部消します')">@csrf<button class="btn btn-sm btn-outline-secondary">記録を消す</button></form>
  </div>
  <div id="logs">
    @foreach ($logs as $l)
      <details class="log side-{{ $l->side }} lv-{{ $l->level }}" data-side="{{ $l->side }}" data-level="{{ $l->level }}" data-id="{{ $l->id }}">
        <summary><span class="time">{{ substr($l->created_at, 11, 8) }}</span><span class="side">{{ $sides[$l->side] ?? $l->side }}</span><span class="lv">{{ ['ok' => 'OK', 'ng' => 'NG'][$l->level] ?? '' }}</span><span class="t">{{ $l->title }}</span></summary>
        @if ($l->detail)<pre>{{ $l->detail }}</pre>@endif
      </details>
    @endforeach
  </div>
@endsection
@push('scripts')
<script>
  const SIDES = @json($sides);
  const esc = (s) => String(s).replace(/[&<>"]/g, (c) => ({ '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;' }[c]));
  const box = document.getElementById('logs'), filter = document.getElementById('filter');
  function applyFilter() {
    const f = filter.value;
    box.querySelectorAll('.log').forEach((el) => { el.style.display = !f || el.dataset.side === f || (f === 'ng' && el.dataset.level === 'ng') ? '' : 'none'; });
  }
  filter.addEventListener('change', applyFilter);
  setInterval(async () => {
    if (!document.getElementById('auto').checked) return;
    const first = box.querySelector('.log');
    const rows = await fetch('{{ route('inside.logs') }}?after=' + (first ? first.dataset.id : 0)).then((r) => r.json()).catch(() => []);
    rows.reverse().forEach((l) => {
      const lv = { ok: 'OK', ng: 'NG' }[l.level] || '';
      box.insertAdjacentHTML('afterbegin', `<details class="log side-${l.side} lv-${l.level} new" data-side="${l.side}" data-level="${l.level}" data-id="${l.id}">
        <summary><span class="time">${l.created_at.slice(11, 19)}</span><span class="side">${SIDES[l.side] || l.side}</span><span class="lv">${lv}</span><span class="t">${esc(l.title)}</span></summary>
        ${l.detail ? `<pre>${esc(l.detail)}</pre>` : ''}</details>`);
    });
    if (rows.length) applyFilter();
  }, 1500);
</script>
@endpush
