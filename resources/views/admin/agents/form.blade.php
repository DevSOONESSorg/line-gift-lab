@extends('layouts.admin')
@section('title', '代理店')
@section('content')
<p><a href="{{ route('admin.agents.index') }}">← 代理店管理</a></p>
<h1 class="h3 mb-3">{{ $agent->exists ? '代理店を編集' : '代理店を登録' }}</h1>
<form method="post" action="{{ $agent->exists ? route('admin.agents.update', $agent) : route('admin.agents.store') }}" class="card card-body" style="max-width:600px">
  @csrf @if ($agent->exists) @method('PUT') @endif
  <label class="form-label">代理店名</label><input name="name" class="form-control mb-2" value="{{ old('name', $agent->name) }}">
  <label class="form-label">会社名</label><input name="company" class="form-control mb-2" value="{{ old('company', $agent->company) }}">
  <label class="form-label">親代理店</label>
  <select name="parent_id" class="form-select mb-1"><option value="">なし（＝1次代理店）</option>
    @foreach ($parents as $p)<option value="{{ $p->id }}" @selected(old('parent_id', $agent->parent_id) == $p->id)>{{ $p->name }}（{{ $p->referral_code }}）</option>@endforeach</select>
  <div class="form-text mb-2">親を選ぶと2次代理店になり、報酬率は 0% になります。</div>
  <label class="form-label">報酬率（売上に対して）</label><div class="input-group mb-2" style="max-width:200px"><input name="reward_rate" type="number" step="0.1" class="form-control" value="{{ old('reward_rate', $agent->reward_rate) }}"><span class="input-group-text">%</span></div>
  <div class="form-check mb-3"><input type="checkbox" class="form-check-input" name="is_active" value="1" id="a" @checked(old('is_active', $agent->is_active))><label class="form-check-label" for="a">有効</label></div>
  @if ($agent->exists)<p class="small">紹介コード：<code>{{ $agent->referral_code }}</code>（自動発行・変更不可）</p>@else<p class="small text-muted">紹介コード（6桁）は登録すると自動で発行されます。</p>@endif
  <button class="btn btn-primary">{{ $agent->exists ? '更新' : '登録する' }}</button>
</form>
@endsection
