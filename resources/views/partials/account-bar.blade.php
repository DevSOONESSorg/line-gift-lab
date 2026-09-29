<div class="account-bar">
  <form method="post" action="{{ route('mock.switch-account') }}" class="d-flex gap-2 align-items-center flex-wrap">
    @csrf
    <input type="hidden" name="back" value="{{ request()->getRequestUri() }}">
    <span>ログイン中：</span>
    <strong class="acc acc-{{ $mockAccount->id }}">{{ $mockAccount->name }}</strong>
    <span class="text-muted small">（{{ $mockAccount->email }}）</span>
    <select name="account_id" class="form-select form-select-sm w-auto" onchange="this.form.submit()">
      @foreach ($mockAccounts as $a)
        <option value="{{ $a->id }}" @selected($a->id === $mockAccount->id)>{{ $a->name }} に切り替え</option>
      @endforeach
    </select>
  </form>
</div>
