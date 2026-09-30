@extends('mock.manager._layout')
@section('title', 'LINE公式アカウントの作成')
@section('page')
<h1 class="h4">LINE公式アカウントの作成</h1>
<form method="post" action="{{ route('mock.manager.store') }}" class="card card-body" style="max-width:600px">@csrf
  <label class="form-label">アカウント名</label><input name="name" class="form-control mb-2" placeholder="新しい店舗名" value="{{ old('name', \App\Services\BuildGuide::current()?->store->name) }}">
  <label class="form-label">メールアドレス</label><input class="form-control mb-2" value="{{ $mockAccount->email }}">
  <label class="form-label">所在国・地域</label><select name="country" class="form-select mb-2"><option value="">選択してください</option><option value="JP">日本</option></select>
  <label class="form-label">業種</label><select name="industry" class="form-select mb-2"><option>飲食</option><option>ナイトワーク</option><option>美容</option><option>その他</option></select>
  <label class="form-label">運用目的</label><select class="form-select mb-2"><option>販促・集客</option></select>
  <label class="form-label">ビジネスマネージャー</label><select class="form-select mb-2"><option>組織を作成</option></select>
  <div class="form-check mb-3"><input class="form-check-input" type="checkbox" name="agree" value="1" id="ag"><label class="form-check-label" for="ag">LINE公式アカウント利用規約に同意する</label></div>
  <button class="btn btn-line px-4">完了</button>
</form>
<p class="small text-muted mt-2">作った人（いまログイン中のアカウント）が、この公式アカウントの「管理者」になります。</p>
@endsection
