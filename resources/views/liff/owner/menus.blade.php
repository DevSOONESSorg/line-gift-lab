@extends('layouts.liff')
@section('theme', 'light')
@section('title', '商品管理')
@section('content')
  @include('liff.owner._nav')
  @forelse ($menus as $m)
    <div class="l-card d-flex justify-content-between align-items-center">
      <span>{{ $m->name }} <strong>¥{{ number_format($m->price) }}</strong> @if (! $m->is_active)<span class="badge text-bg-secondary">停止中</span>@endif</span>
      <form method="post" action="{{ route('liff.manage.menus.toggle', [$store, $m]) }}">@csrf<button class="btn btn-sm btn-outline-secondary">{{ $m->is_active ? '停止' : '再開' }}</button></form>
    </div>
  @empty
    <div class="alert alert-warning small">まだ商品がありません。1つ以上ないと、ギフトを贈ってもらえません。</div>
  @endforelse

  <h2 class="h6 mt-3">テンプレートから追加</h2>
  <form method="post" action="{{ route('liff.manage.menus.add', $store) }}" class="mb-3">@csrf
    <select name="template_id" class="form-select mb-2">
      @foreach ($templates as $cat => $list)<optgroup label="{{ $cat }}">@foreach ($list as $t)<option value="{{ $t->id }}">{{ $t->name }}（参考 ¥{{ number_format($t->reference_price) }}）</option>@endforeach</optgroup>@endforeach
    </select>
    <div class="d-flex gap-2"><input name="price" type="number" class="form-control" placeholder="価格（空なら参考価格）"><button class="btn btn-warning fw-bold text-nowrap">追加</button></div>
  </form>
  <h2 class="h6">自由に追加</h2>
  <form method="post" action="{{ route('liff.manage.menus.add', $store) }}">@csrf
    <input name="name" class="form-control mb-2" placeholder="商品名（例：スタッフ乾杯用）">
    <div class="d-flex gap-2"><input name="price" type="number" class="form-control" placeholder="価格（円）"><button class="btn btn-warning fw-bold text-nowrap">追加</button></div>
  </form>
@endsection
