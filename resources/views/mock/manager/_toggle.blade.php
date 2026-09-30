{{-- 応答設定のスイッチ1つ。切り替えるとすぐ送信（その項目だけ保存） --}}
<form method="post" action="{{ route('mock.manager.oa.response.save', $oa) }}" class="m-0">@csrf
  <input type="hidden" name="{{ $name }}" value="{{ $on ? '0' : '1' }}">
  <div class="form-check form-switch m-0"><input class="form-check-input" type="checkbox" role="switch" name="t_{{ $name }}" @checked($on) @disabled(! $enabled) onchange="this.form.submit()"></div>
</form>
