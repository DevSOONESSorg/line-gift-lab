@extends('layouts.admin')
@section('title', $store->name)
@section('content')
<p><a href="{{ route('admin.stores.index') }}">← 店舗管理</a></p>
<div class="d-flex justify-content-between align-items-center mb-3">
  <h1 class="h3 mb-0">{{ $store->name }}</h1>
  <div class="d-flex gap-2">
    <a class="btn btn-primary" href="{{ route('admin.stores.edit', $store) }}">編集</a>
    <form method="post" action="{{ route('admin.stores.destroy', $store) }}" data-confirm="「{{ $store->name }}」を削除しますか？元に戻せません。">@csrf @method('DELETE')<button class="btn btn-outline-danger">削除</button></form>
  </div>
</div>
<div class="row g-3">
  <div class="col-lg-8">
    <div class="card mb-3"><div class="card-header">基本情報</div><div class="card-body"><dl class="row mb-0">
      <dt class="col-3">ID</dt><dd class="col-9">{{ $store->id }}</dd>
      <dt class="col-3">スラッグ</dt><dd class="col-9"><code>{{ $store->slug }}</code></dd>
      <dt class="col-3">説明</dt><dd class="col-9">{{ $store->description ?: '—' }}</dd>
      <dt class="col-3">手数料率</dt><dd class="col-9">{{ rtrim(rtrim($store->commission_rate, '0'), '.') }}%（今月 {{ \App\Services\CommissionService::rateFor($store) }}%）</dd>
      <dt class="col-3">代理店</dt><dd class="col-9">{{ $store->agent ? "{$store->agent->name}（{$store->agent->referral_code}）" : '—' }}</dd>
      <dt class="col-3">ステータス</dt><dd class="col-9"><span class="badge text-bg-{{ $store->is_approved ? 'success' : 'warning' }}">{{ $store->is_approved ? '承認済み' : '未承認' }}</span>
        {!! $store->is_listed_in_directory ? '<span class="badge text-bg-info">共通掲載</span>' : '' !!}</dd>
      <dt class="col-3">振込先</dt><dd class="col-9">{{ $store->maskedBankAccount() }}</dd>
      <dt class="col-3">登録日</dt><dd class="col-9">{{ $store->created_at->format('Y/m/d H:i') }}</dd>
    </dl></div></div>
    <div class="card mb-3"><div class="card-header d-flex justify-content-between align-items-center">メニュー一覧 <a class="btn btn-sm btn-primary" href="{{ route('admin.stores.menus.create', $store) }}">+ 追加</a></div>
      <table class="table mb-0 align-middle"><thead><tr><th>メニュー名</th><th>価格</th><th>ステータス</th><th></th></tr></thead><tbody>
      @forelse ($store->menus as $m)
        <tr><td>{{ $m->name }} @if ($m->menu_template_id)<span class="badge text-bg-light border">テンプレート</span>@endif</td><td>¥{{ number_format($m->price) }}</td>
          <td><span class="badge text-bg-{{ $m->is_active ? 'success' : 'secondary' }}">{{ $m->is_active ? '販売中' : '停止中' }}</span></td>
          <td><a class="btn btn-sm btn-outline-primary" href="{{ route('admin.stores.menus.edit', [$store, $m]) }}">編集</a></td></tr>
      @empty
        <tr><td colspan="4" class="text-center text-muted">商品がありません（これが無いと贈れません）</td></tr>
      @endforelse
      </tbody></table></div>
  </div>
  <div class="col-lg-4">
    <div class="card mb-3"><div class="card-header">管理者（オーナー）</div><div class="card-body"><dl class="row mb-0 small">
      <dt class="col-4">名前</dt><dd class="col-8">{{ $store->representative_name ?: '—' }}</dd>
      <dt class="col-4">LINE表示名</dt><dd class="col-8">{{ $store->owner?->line_display_name ?? '—' }}</dd>
      <dt class="col-4">LINE ID</dt><dd class="col-8"><code>{{ $store->owner_line_user_id ?: '—' }}</code></dd>
    </dl></div></div>
    <div class="card mb-3"><div class="card-header">売上サマリー</div><div class="card-body"><dl class="row mb-0">
      <dt class="col-6">総売上</dt><dd class="col-6">¥{{ number_format($sales) }}</dd>
      <dt class="col-6">手数料</dt><dd class="col-6">¥{{ number_format($commission) }}</dd>
      <dt class="col-6">振込額</dt><dd class="col-6">¥{{ number_format($sales - $commission) }}</dd>
    </dl><p class="small text-muted mb-0">「受取済み」「お礼済み」の注文だけを数えています。</p></div></div>
    <div class="card"><div class="card-header">店舗紐づけQRコード</div><div class="card-body text-center">
      @php $qrUrl = $store->liff_id ? "https://liff.line.me/{$store->liff_id}" : url("/liff/s/{$store->slug}"); @endphp
      <div id="storeqr" class="d-inline-block"></div>
      <div class="small mt-2"><code>{{ $qrUrl }}</code> <button class="btn btn-sm btn-outline-secondary" onclick="navigator.clipboard.writeText('{{ $qrUrl }}'); this.textContent='コピーしました'">コピー</button></div>
      <p class="small text-muted mt-2 mb-1">{{ $store->liff_id ? 'お店専用の LIFF で開きます' : 'LIFF ID が未設定なので、共通のページで開きます' }}</p>
      <a class="btn btn-sm btn-outline-primary" href="{{ route('mock.phone', ['open' => $qrUrl]) }}">疑似スマホで読み取る</a>
    </div></div>
  </div>
</div>
@endsection
@push('scripts')
<script>new QRCode(document.getElementById('storeqr'), { text: @json($qrUrl), width: 160, height: 160 });</script>
@endpush
