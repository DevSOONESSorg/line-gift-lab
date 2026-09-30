@extends('layouts.liff')
@section('theme', 'light')
@section('title', '出店登録')
@section('content')
  <div class="l-h">出店登録</div>
  <p class="small">おくりギフトに、あなたのお店を登録します。登録したLINEアカウントが、そのお店の「オーナー」になります（ギフトの受け取り・商品登録ができる人）。</p>
  <form method="post" action="{{ route('liff.register.post') }}">
    @csrf
    <h2 class="h6 mt-3">1. お店の情報</h2>
    <input name="name" class="form-control mb-2" placeholder="店名 *" value="{{ old('name') }}">
    <textarea name="description" class="form-control mb-2" rows="2" placeholder="お店の紹介（任意）">{{ old('description') }}</textarea>
    <div class="d-flex gap-2 mb-2"><input name="postal_code" class="form-control" placeholder="郵便番号" value="{{ old('postal_code') }}"><input name="prefecture" class="form-control" placeholder="都道府県 *" value="{{ old('prefecture', '沖縄県') }}"></div>
    <div class="d-flex gap-2 mb-2"><input name="city" class="form-control" placeholder="市区町村 *" value="{{ old('city') }}"><input name="address" class="form-control" placeholder="番地・建物名" value="{{ old('address') }}"></div>
    <input name="tel" class="form-control mb-2" placeholder="お店の電話番号 *" value="{{ old('tel') }}">
    <input name="business_license_number" class="form-control mb-2" placeholder="営業許可証番号（任意）" value="{{ old('business_license_number') }}">

    <h2 class="h6 mt-3">2. 代表者（あなた）</h2>
    <div class="d-flex gap-2 mb-2"><input name="representative_name" class="form-control" placeholder="お名前 *" value="{{ old('representative_name') }}"><input name="representative_tel" class="form-control" placeholder="電話番号 *" value="{{ old('representative_tel') }}"></div>

    <h2 class="h6 mt-3">3. 振込先（売上の振込先）</h2>
    <div class="alert alert-warning small py-1">体験用です。<strong>本物の口座番号は入れないでください</strong>（例：1234567）。</div>
    <div class="d-flex gap-2 mb-2"><input name="bank_name" class="form-control" placeholder="銀行名 *" value="{{ old('bank_name') }}"><input name="bank_branch" class="form-control" placeholder="支店名" value="{{ old('bank_branch') }}"></div>
    <div class="d-flex gap-2 mb-2"><select name="bank_account_type" class="form-select" style="max-width:100px"><option>普通</option><option @selected(old('bank_account_type') === '当座')>当座</option></select>
      <input name="bank_account_number" class="form-control" placeholder="口座番号（7桁）" value="{{ old('bank_account_number') }}" inputmode="numeric"></div>
    <input name="bank_account_name" class="form-control mb-2" placeholder="口座名義（カナ） *" value="{{ old('bank_account_name') }}">
    <details class="small mb-2"><summary>ゆうちょ銀行の場合</summary>
      <p class="mb-1">銀行名に「ゆうちょ銀行」と入れ、記号と番号を入力してください（支店名・口座番号は不要）。</p>
      <div class="d-flex gap-2"><input name="yucho_symbol" class="form-control" placeholder="記号（5桁）" value="{{ old('yucho_symbol') }}"><input name="yucho_number" class="form-control" placeholder="番号" value="{{ old('yucho_number') }}"></div>
    </details>

    <h2 class="h6 mt-3">4. 掲載方法</h2>
    <label class="menu"><input type="radio" name="publish" value="common" @checked(old('publish', 'common') === 'common')><span><strong>共通掲載</strong><br><span class="small text-muted">おくりギフトの「お店をさがす」に、お店が並びます</span></span></label>
    <label class="menu"><input type="radio" name="publish" value="original" @checked(old('publish') === 'original')><span><strong>オリジナル</strong><br><span class="small text-muted">お店の公式LINEを用意し、そのリッチメニューから贈れるようにします（運営が設定をお手伝いします）</span></span></label>
    <button class="btn btn-warning fw-bold w-100 mt-3">送信</button>
  </form>
@endsection
