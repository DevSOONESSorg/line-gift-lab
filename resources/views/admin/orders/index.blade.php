@extends('layouts.admin')
@section('title', '注文管理')
@section('content')
<h1 class="h3 mb-3">注文管理</h1>
<form class="row g-2 mb-3" method="get">
  <div class="col-auto"><select name="status" class="form-select"><option value="">すべてのステータス</option>
    @foreach ($statuses as $s)<option value="{{ $s->value }}" @selected(request('status') === $s->value)>{{ $s->label() }}</option>@endforeach</select></div>
  <div class="col-auto"><select name="payment_method" class="form-select"><option value="">決済方法すべて</option>
    @foreach ($methods as $m)<option value="{{ $m->value }}" @selected(request('payment_method') === $m->value)>{{ $m->value === 'card' ? 'クレジットカード' : '銀行振込' }}</option>@endforeach</select></div>
  <div class="col-auto"><input type="date" name="start_date" class="form-control" value="{{ request('start_date') }}"></div>
  <div class="col-auto"><input type="date" name="end_date" class="form-control" value="{{ request('end_date') }}"></div>
  <div class="col-auto"><button class="btn btn-secondary">絞り込み</button></div>
</form>
<div class="card">@include('admin.orders._table')</div>
<div class="mt-3">{{ $orders->links('pagination::bootstrap-5') }}</div>
@endsection
