@extends('mock.manager._layout')
@section('title', 'アカウント設定')
@section('page')
<h1 class="h4">アカウント設定</h1>
<div class="card card-body"><dl class="row mb-0">
  <dt class="col-3">アカウント名</dt><dd class="col-9">{{ $oa->name }}</dd>
  <dt class="col-3">ベーシックID</dt><dd class="col-9"><code>{{ $oa->basic_id }}</code> <span class="small text-muted">← 管理画面の「LINE公式アカウントID (@xxx)」に入れる値</span></dd>
  <dt class="col-3">業種</dt><dd class="col-9">{{ $oa->industry ?: '—' }}</dd>
  <dt class="col-3">作成日</dt><dd class="col-9">{{ $oa->created_at }}</dd>
</dl></div>
@endsection
