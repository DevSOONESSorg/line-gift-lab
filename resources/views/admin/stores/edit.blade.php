@extends('layouts.admin')
@section('title', '店舗編集')
@section('content')
<p><a href="{{ route('admin.stores.show', $store) }}">← {{ $store->name }}</a></p>
<h1 class="h3 mb-3">店舗編集</h1>

<form method="post" action="{{ route('admin.stores.update', $store) }}">
  @csrf @method('PUT')
  <div class="row g-3">
    <div class="col-lg-6">
      <div class="card mb-3"><div class="card-header">基本情報</div><div class="card-body">
        <label class="form-label">店舗名 *</label><input name="name" class="form-control mb-2" value="{{ old('name', $store->name) }}">
        <label class="form-label">スラッグ *</label><input name="slug" class="form-control" value="{{ old('slug', $store->slug) }}">
        <div class="form-text mb-2">URLに使用されます。一度決めたら変えないこと（LIFFのエンドポイントURLとWebhook URLに入っている）</div>
        <label class="form-label">説明</label><textarea name="description" class="form-control mb-2" rows="2">{{ old('description', $store->description) }}</textarea>
        <label class="form-label">WebサイトURL</label><input name="website_url" type="url" class="form-control" value="{{ old('website_url', $store->website_url) }}">
      </div></div>

      <div class="card mb-3"><div class="card-header">所在地</div><div class="card-body row g-2">
        <div class="col-4"><label class="form-label">郵便番号</label><input name="postal_code" class="form-control" value="{{ old('postal_code', $store->postal_code) }}"></div>
        <div class="col-8"><label class="form-label">都道府県</label><input name="prefecture" class="form-control" value="{{ old('prefecture', $store->prefecture) }}"></div>
        <div class="col-6"><label class="form-label">市区町村</label><input name="city" class="form-control" value="{{ old('city', $store->city) }}"></div>
        <div class="col-6"><label class="form-label">番地・建物名</label><input name="address" class="form-control" value="{{ old('address', $store->address) }}"></div>
        <div class="col-6"><label class="form-label">電話番号</label><input name="tel" class="form-control" value="{{ old('tel', $store->tel) }}"></div>
      </div></div>

      <div class="card mb-3"><div class="card-header">代表者情報・営業許可情報</div><div class="card-body row g-2">
        <div class="col-6"><label class="form-label">代表者名</label><input name="representative_name" class="form-control" value="{{ old('representative_name', $store->representative_name) }}"></div>
        <div class="col-6"><label class="form-label">代表者電話番号</label><input name="representative_tel" class="form-control" value="{{ old('representative_tel', $store->representative_tel) }}"></div>
        <div class="col-12"><label class="form-label">営業許可証番号</label><input name="business_license_number" class="form-control" value="{{ old('business_license_number', $store->business_license_number) }}"></div>
      </div></div>

      <div class="card mb-3"><div class="card-header">振込先情報</div><div class="card-body row g-2">
        <div class="col-6"><label class="form-label">銀行名</label><input name="bank_name" class="form-control" value="{{ old('bank_name', $store->bank_name) }}"></div>
        <div class="col-6"><label class="form-label">支店名</label><input name="bank_branch" class="form-control" value="{{ old('bank_branch', $store->bank_branch) }}"></div>
        <div class="col-4"><label class="form-label">口座種別</label><select name="bank_account_type" class="form-select"><option value=""></option>
          @foreach (['普通', '当座'] as $t)<option @selected(old('bank_account_type', $store->bank_account_type) === $t)>{{ $t }}</option>@endforeach</select></div>
        <div class="col-8"><label class="form-label">口座番号</label><input name="bank_account_number" class="form-control" value="{{ old('bank_account_number', $store->bank_account_number) }}"></div>
        <div class="col-12"><label class="form-label">口座名義</label><input name="bank_account_name" class="form-control" value="{{ old('bank_account_name', $store->bank_account_name) }}"></div>
        <div class="col-12 form-text">ゆうちょ銀行の場合は以下も入力してください</div>
        <div class="col-6"><label class="form-label">ゆうちょ記号</label><input name="yucho_symbol" class="form-control" value="{{ old('yucho_symbol', $store->yucho_symbol) }}"></div>
        <div class="col-6"><label class="form-label">ゆうちょ番号</label><input name="yucho_number" class="form-control" value="{{ old('yucho_number', $store->yucho_number) }}"></div>
      </div></div>
    </div>

    <div class="col-lg-6">
      <div class="card mb-3"><div class="card-header">管理設定</div><div class="card-body">
        <label class="form-label">紐付け代理店</label>
        <select name="agent_id" class="form-select"><option value="">紐付けなし</option>
          @foreach ($agents as $a)<option value="{{ $a->id }}" @selected(old('agent_id', $store->agent_id) == $a->id)>{{ $a->parent_id ? '　└ ' : '' }}{{ $a->name }}（{{ $a->referral_code }}）</option>@endforeach
        </select>
        <div class="form-text mb-2">後から代理店を紐付け・変更できます。「紐付けなし」で解除されます。選ぶときは紹介コードまで確認。</div>
        <label class="form-label">デフォルト手数料率 *</label>
        <div class="input-group mb-1"><input name="commission_rate" type="number" step="0.1" class="form-control" value="{{ old('commission_rate', $store->commission_rate) }}"><span class="input-group-text">%</span></div>
        <div class="form-text mb-3">月別設定がない場合に使用</div>
        <div class="form-check form-switch mb-2"><input class="form-check-input" type="checkbox" name="is_approved" value="1" id="ap" @checked($store->is_approved)><label class="form-check-label" for="ap">承認済み</label></div>
        <div class="form-check form-switch"><input class="form-check-input" type="checkbox" name="is_listed_in_directory" value="1" id="li" @checked($store->is_listed_in_directory)><label class="form-check-label" for="li">共通アプリの店舗一覧に掲載する</label></div>
        <div class="form-text mb-2">オフにすると、共通アプリの検索結果から店舗が表示されなくなります。お店専用URL／QRコードからのアクセスは引き続き可能です。</div>
        @if ($store->wants_original)<div class="alert alert-info py-1 small">このお店は出店登録で「オリジナル（お店の公式LINE）」を希望しています。</div>@endif
        <div class="form-check form-switch"><input class="form-check-input" type="checkbox" name="require_id_verification" value="1" id="idv" @checked($store->require_id_verification)><label class="form-check-label" for="idv">贈り物送信時に身分証提示を必須にする</label></div>
        <div class="form-text">オンにすると、まだ身分証を登録していない利用者がこの店舗に贈ろうとした際、画像アップロード画面が表示されます。一度登録した身分証は他の店舗でも有効です。</div>
      </div></div>

      <div class="card mb-3" id="line"><div class="card-header">LINE 連携設定 (店舗専用チャネル)</div><div class="card-body">
        <p class="form-text">オリジナル（お店の公式LINE）で贈れるようにするお店だけ設定します。空欄のお店は、共通のLIFF・共通チャネルが使われます。</p>
        <a class="btn btn-sm btn-warning mb-3" href="{{ route('build', ['store' => $store->id]) }}"><i class="bi bi-signpost-split"></i> このお店を構築ナビで進める</a>
        <label class="form-label">LIFF ID (LINEログインチャネル)</label><input name="liff_id" class="form-control mb-2" value="{{ old('liff_id', $store->liff_id) }}" placeholder="2009896760-NLWo39Yw">
        <label class="form-label">Messaging API チャネルID</label><input name="line_messaging_channel_id" class="form-control mb-2" value="{{ old('line_messaging_channel_id', $store->line_messaging_channel_id) }}">
        <label class="form-label">Messaging API チャネルシークレット <span class="badge text-bg-{{ $store->line_messaging_channel_secret ? 'success' : 'secondary' }}">{{ $store->line_messaging_channel_secret ? '設定済み' : '未設定' }}</span></label>
        <input name="line_messaging_channel_secret" type="password" class="form-control" autocomplete="off">
        <div class="form-text mb-2">空欄で保存すると現在の値を維持します。DBには暗号化して保存されます。</div>
        <label class="form-label">Messaging API チャネルアクセストークン (長期) <span class="badge text-bg-{{ $store->line_messaging_channel_access_token ? 'success' : 'secondary' }}">{{ $store->line_messaging_channel_access_token ? '設定済み' : '未設定' }}</span></label>
        <textarea name="line_messaging_channel_access_token" class="form-control" rows="2" autocomplete="off"></textarea>
        <div class="form-text mb-2">空欄で保存すると現在の値を維持します。</div>
        <label class="form-label">LINE公式アカウントID (@xxx)</label><input name="line_official_account_id" class="form-control mb-2" value="{{ old('line_official_account_id', $store->line_official_account_id) }}">
        <div class="form-check form-switch"><input class="form-check-input" type="checkbox" name="auto_reply_enabled" value="1" id="ar" @checked($store->auto_reply_enabled)><label class="form-check-label" for="ar">テキスト受信時にギフト誘導を自動返信する</label></div>
        <div class="form-text">オンにすると、この店舗の専用LINEチャネルにメッセージが届くたびにギフトリンクを自動返信します。オフにすると、通常の問い合わせには反応しません。</div>
      </div></div>
    </div>
  </div>
  <div class="d-flex gap-2 mb-4"><button class="btn btn-primary px-5">更新</button><a class="btn btn-outline-secondary" href="{{ route('admin.stores.show', $store) }}">キャンセル</a></div>
</form>

<div class="card mb-3"><div class="card-header">📡 LINE 連携 確認 / テスト</div><div class="card-body">
  <label class="form-label fw-bold">店舗専用 Webhook URL</label>
  <p class="form-text mb-1">店舗専用 Messaging API チャネルを使う場合、Developers Console の「Webhook URL」欄にこの値を貼ってください。</p>
  @foreach (array_filter(['疑似LINE（コースA）' => $webhookLocal, '本物のLINE（コースB・トンネル）' => $webhookPublic]) as $label => $url)
    <div class="input-group mb-2"><span class="input-group-text small">{{ $label }}</span><input class="form-control font-monospace small" value="{{ $url }}" readonly><button type="button" class="btn btn-outline-secondary" onclick="navigator.clipboard.writeText('{{ $url }}'); this.textContent='コピーしました'">コピー</button></div>
  @endforeach
  <label class="form-label fw-bold mt-2">LIFF のエンドポイントURL</label>
  @foreach (array_filter(['疑似LINE' => $liffLocal, '本物のLINE' => $liffPublic]) as $label => $url)
    <div class="input-group mb-2"><span class="input-group-text small">{{ $label }}</span><input class="form-control font-monospace small" value="{{ $url }}" readonly><button type="button" class="btn btn-outline-secondary" onclick="navigator.clipboard.writeText('{{ $url }}'); this.textContent='コピーしました'">コピー</button></div>
  @endforeach

  <div class="d-flex justify-content-between align-items-center mt-3"><span class="fw-bold">設定整合性</span>
    <form method="post" action="{{ route('admin.stores.check', $store) }}">@csrf<button class="btn btn-sm btn-outline-primary">チェックする</button></form></div>
  @if ($checks)
    <ul class="list-group mt-2">
      @foreach ($checks as $c)
        <li class="list-group-item {{ $c['ok'] ? '' : 'list-group-item-danger' }}"><span class="badge text-bg-{{ $c['ok'] ? 'success' : 'danger' }} me-2">{{ $c['ok'] ? 'OK' : 'NG' }}</span>{{ $c['label'] }}
          @if ($c['hint'])<div class="small text-muted">{{ $c['hint'] }}</div>@endif</li>
      @endforeach
    </ul>
  @endif

  <form method="post" action="{{ route('admin.stores.test-line', $store) }}" id="test-line-form" class="mt-3">@csrf
    <label class="form-label fw-bold">テスト送信</label>
    <p class="form-text mb-1">設定した Messaging API トークンで送信できるか確認します。送信先は LINE userId（Uから始まる文字列）。受信者はその店舗の公式アカウントを友だち追加している必要があります。</p>
    <div class="input-group"><input name="line_id" class="form-control font-monospace" placeholder="U0123...（疑似スマホのホームに出ている、ユーザーID）" value="{{ old('line_id') }}"><button class="btn btn-outline-success">テスト送信</button></div>
  </form>
</div></div>

<div class="card mb-3" id="rates"><div class="card-header">月別手数料率</div><div class="card-body">
  <form method="post" action="{{ route('admin.stores.rates', $store) }}">@csrf
    <table class="table table-sm align-middle"><thead><tr><th>月</th><th>手数料率</th></tr></thead><tbody>
    @foreach ($months as $i => $m)
      <tr><td>{{ $m->year }}年{{ $m->month }}月</td><td>
        <input type="hidden" name="rates[{{ $i }}][year]" value="{{ $m->year }}"><input type="hidden" name="rates[{{ $i }}][month]" value="{{ $m->month }}">
        <div class="input-group input-group-sm" style="max-width:160px"><input type="number" step="0.1" name="rates[{{ $i }}][rate]" class="form-control" value="{{ optional($rates->get("{$m->year}-{$m->month}"))->rate }}"><span class="input-group-text">%</span></div>
      </td></tr>
    @endforeach
    </tbody></table>
    <button class="btn btn-sm btn-primary">月別手数料率を保存</button>
    <div class="form-text">空欄の場合はデフォルト手数料率が適用されます。注文には「注文した時点の率」が保存されます。</div>
  </form>
</div></div>

<div class="card"><div class="card-header">システム情報</div><div class="card-body small"><dl class="row mb-0">
  <dt class="col-3">ID</dt><dd class="col-9">{{ $store->id }}</dd>
  <dt class="col-3">登録日</dt><dd class="col-9">{{ $store->created_at->format('Y/m/d H:i') }}</dd>
  <dt class="col-3">更新日</dt><dd class="col-9">{{ $store->updated_at->format('Y/m/d H:i') }}</dd>
</dl></div></div>
@endsection
