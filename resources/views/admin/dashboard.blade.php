@extends('layouts.admin')
@section('title', 'ダッシュボード')
@section('content')
<h1 class="h3 mb-4">ダッシュボード</h1>
<div class="row g-3 mb-4">
  <div class="col-md-3"><div class="card stat border-start border-4 border-primary"><div class="card-body"><div class="text-muted small">店舗数</div><div class="fs-3">{{ $storeCount }}</div>
    @if ($pendingStores)<a class="small text-warning" href="{{ route('admin.stores.index', ['status' => 'pending']) }}">{{ $pendingStores }}件 未承認</a>@endif</div></div></div>
  <div class="col-md-3"><div class="card stat border-start border-4 border-success"><div class="card-body"><div class="text-muted small">注文数</div><div class="fs-3">{{ $orderCount }}</div></div></div></div>
  <div class="col-md-3"><div class="card stat border-start border-4 border-warning"><div class="card-body"><div class="text-muted small">ユーザー数</div><div class="fs-3">{{ $customerCount }}</div></div></div></div>
  <div class="col-md-3"><div class="card stat border-start border-4 border-danger"><div class="card-body"><div class="text-muted small">総売上</div><div class="fs-3">¥{{ number_format($totalSales) }}</div></div></div></div>
</div>
<div class="card"><div class="card-header">最近の注文</div>
  @include('admin.orders._table', ['orders' => $recentOrders])
</div>
@endsection
