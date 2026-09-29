<div class="table-responsive"><table class="table table-hover mb-0 align-middle">
  <thead><tr><th>ID</th><th>店舗</th><th>メニュー</th><th>金額</th><th>送り主</th><th>決済方法</th><th>ステータス</th><th>お礼動画</th><th>注文日時</th><th></th></tr></thead>
  <tbody>
  @forelse ($orders as $o)
    <tr>
      <td>{{ $o->id }}</td>
      <td><a href="{{ route('admin.stores.show', $o->store) }}">{{ $o->store->name }}</a></td>
      <td>{{ $o->menu_name }}</td>
      <td>¥{{ number_format($o->amount) }}</td>
      <td><a href="{{ route('admin.users.show', $o->customer) }}">{{ $o->customer->name ?: $o->customer->line_display_name }}</a></td>
      <td><span class="badge text-bg-light border">{{ $o->payment_method->label() }}</span></td>
      <td><span class="badge text-bg-{{ $o->status->color() }}">{{ $o->status->label() }}</span></td>
      <td>{!! $o->thank_video_path ? '<i class="bi bi-camera-video text-success"></i>' : '—' !!}</td>
      <td class="small">{{ $o->created_at->format('Y/m/d H:i') }}</td>
      <td><a class="btn btn-sm btn-outline-primary" href="{{ route('admin.orders.show', $o) }}">詳細</a></td>
    </tr>
  @empty
    <tr><td colspan="10" class="text-center text-muted py-4">注文はまだありません</td></tr>
  @endforelse
  </tbody>
</table></div>
