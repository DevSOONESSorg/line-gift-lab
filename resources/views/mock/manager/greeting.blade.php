@extends('mock.manager._layout')
@section('title', 'あいさつメッセージ')
@section('page')
<h1 class="h4">あいさつメッセージ</h1>
<p class="small text-muted">友だち追加されたときに送るメッセージです。送るかどうか（オン・オフ）は「<a href="{{ route('mock.manager.oa.response', $oa) }}">応答設定</a>」で切り替えます（いま：<b>{{ $oa->greeting_on ? 'オン' : 'オフ' }}</b>）。</p>
<form method="post" action="{{ route('mock.manager.oa.greeting.save', $oa) }}" class="card card-body" style="max-width:760px">@csrf
  <textarea name="greeting_text" class="form-control mb-2" rows="4">{{ $oa->greeting_text }}</textarea>
  <div class="form-text mb-2"><code>{Nickname}</code> は友だちの表示名、<code>{AccountName}</code> はアカウント名に置きかわります。</div>
  <button class="btn btn-line align-self-start px-4">保存</button>
</form>
@endsection
