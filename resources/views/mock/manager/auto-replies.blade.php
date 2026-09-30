@extends('mock.manager._layout')
@section('title', '応答メッセージ')
@section('page')
<div class="d-flex align-items-center mb-2"><h1 class="h4 mb-0">応答メッセージ</h1><a class="btn btn-success btn-sm ms-auto" href="?create=1">作成</a></div>
<p class="small text-muted">キーワードと完全に一致したメッセージに、LINE社が自動で返します。キーワードが空のもの（一律応答）は、ほかのキーワードに当たらなかったメッセージすべてに返します。
  ここで「利用」がオンでも、「<a href="{{ route('mock.manager.oa.response', $oa) }}">応答設定</a>」の応答メッセージがオフだと、どれも返りません（いま：<b>{{ $oa->auto_reply_on ? 'オン' : 'オフ' }}</b>）。</p>
@if ($creating || $edit)
  <form method="post" action="{{ route('mock.manager.oa.auto-replies.save', $oa) }}" class="card card-body mb-3" style="max-width:760px">@csrf
    @if ($edit)<input type="hidden" name="id" value="{{ $edit->id }}">@endif
    <label class="form-label">タイトル（管理用）</label><input name="title" class="form-control mb-2" value="{{ old('title', $edit?->title) }}">
    <label class="form-label">キーワード（1行に1つ。空なら一律応答）</label><textarea name="keywords" class="form-control mb-2" rows="2">{{ old('keywords', $edit?->keywords) }}</textarea>
    <label class="form-label">メッセージ</label><textarea name="text" class="form-control mb-2" rows="3">{{ old('text', $edit?->text) }}</textarea>
    <div class="d-flex gap-2"><button class="btn btn-line px-4">保存</button><a class="btn btn-light" href="?">キャンセル</a></div>
  </form>
@endif
<table class="table bg-white align-middle">
  <thead><tr><th>タイトル</th><th>キーワード</th><th>メッセージ</th><th class="text-nowrap">利用</th><th></th></tr></thead>
  <tbody>
  @forelse ($items as $a)
    <tr class="{{ $a->enabled ? '' : 'text-muted' }}">
      <td>{{ $a->title }}</td>
      <td class="small">{!! $a->isCatchAll() ? '<span class="badge text-bg-secondary">一律応答</span>' : e(implode('、', $a->keywordList())) !!}</td>
      <td class="small" style="white-space:pre-line;max-width:360px">{{ \Illuminate\Support\Str::limit($a->text, 80) }}</td>
      <td><form method="post" action="{{ route('mock.manager.oa.auto-replies.toggle', [$oa, $a]) }}" class="m-0">@csrf<div class="form-check form-switch m-0"><input class="form-check-input" type="checkbox" role="switch" @checked($a->enabled) onchange="this.form.submit()"></div></form></td>
      <td class="text-nowrap"><a class="btn btn-sm btn-link" href="?edit={{ $a->id }}">編集</a>
        <form method="post" action="{{ route('mock.manager.oa.auto-replies.delete', [$oa, $a]) }}" class="d-inline" onsubmit="return confirm('削除しますか？')">@csrf @method('DELETE')<button class="btn btn-sm btn-link text-danger">削除</button></form></td>
    </tr>
  @empty
    <tr><td colspan="5" class="text-center text-muted py-3">応答メッセージはありません</td></tr>
  @endforelse
  </tbody>
</table>
@endsection
