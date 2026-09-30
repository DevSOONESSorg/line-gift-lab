{{--
  構築ナビの手順1つぶん。
    完了   … 緑のチェック。タイトルだけ（押すと中身を見返せる）
    いまここ … オレンジの枠。やることを1行ずつ（開く・押す・選ぶ・コピー・貼り付け・確認）
    ロック   … グレー。前の手順が終わるまで中身もリンクも出さない（手順を飛ばさないため）
--}}
@php
  $accounts = \App\Services\BuildGuide::ACCOUNTS;
  $labels = \App\Services\BuildGuide::ACTION_LABELS;
  $me = session('mock_account', 'personal');
  $back = request('uri') ?: request()->getRequestUri();
@endphp
<div class="bstep bstep-{{ $st['state'] }}">
  @if ($st['state'] === 'done')
    <details>
      <summary class="bstep-head"><span class="bno"><i class="bi bi-check-lg"></i></span><span class="flex-grow-1"><b>{{ $st['title'] }}</b></span><span class="bdone">完了</span></summary>
      <div class="bstep-body"><ol class="bacts">@foreach ($st['actions'] as $a)<li><span class="bact bact-{{ $a[0] }}">{{ $labels[$a[0]] }}</span> {{ $a[1] }}</li>@endforeach</ol></div>
    </details>
  @elseif ($st['state'] === 'locked')
    <div class="bstep-head"><span class="bno"><i class="bi bi-lock-fill"></i></span><span class="flex-grow-1"><b>{{ $st['title'] }}</b><span class="bwho">前の手順が終わると開きます</span></span></div>
  @else
    <div class="bstep-head">
      <span class="bno">{{ $st['no'] }}</span>
      <span class="flex-grow-1"><b>{{ $st['title'] }}</b><span class="bwho">操作する人：{{ $accounts[$st['who']] }}</span></span>
    </div>
    <div class="bstep-body">
      @if (! empty($st['bad']))<div class="alert alert-danger py-1 small mb-2">{{ $st['bad'] }}</div>@endif
      <ol class="bacts">
        @foreach ($st['actions'] as $a)
          @php [$type, $text] = $a; $val = $a[2] ?? null; $src = $a[3] ?? null; @endphp
          <li>
            <span class="bact bact-{{ $type }}">{{ $labels[$type] }}</span>
            @if ($type === 'open' && $val)
              <a href="{{ $val }}">{{ $text }} <i class="bi bi-box-arrow-up-right"></i></a>
            @elseif ($type === 'switch')
              {{ $text }}
              @if ($val !== $me)
                <form method="post" action="{{ route('mock.switch-account') }}" class="d-inline">@csrf
                  <input type="hidden" name="account_id" value="{{ $val }}"><input type="hidden" name="back" value="{{ str_starts_with($back, '/mock/') ? $back : '/mock/manager' }}">
                  <button class="btn btn-sm btn-warning py-0 px-2">いま切り替える</button></form>
              @else
                <span class="text-success small"><i class="bi bi-check-circle"></i> 切り替え済み</span>
              @endif
            @else
              {{ $text }}
              @if ($src)<a class="small ms-1" href="{{ $src }}">コピー元を開く <i class="bi bi-box-arrow-up-right"></i></a>@endif
            @endif
            @if (in_array($type, ['paste', 'copy'], true) && $val && $type !== 'open')
              <div class="bval"><code class="user-select-all">{{ $val }}</code><button type="button" class="btn btn-sm btn-outline-secondary py-0" onclick="navigator.clipboard.writeText(@js($val)); this.textContent='コピーしました'">コピー</button></div>
            @endif
          </li>
        @endforeach
      </ol>
      <p class="small bwhy mb-2"><i class="bi bi-lightbulb"></i> {{ $st['why'] }}</p>
      <div class="small text-muted mb-2"><i class="bi bi-arrow-repeat"></i> 操作が終わると、このナビが自動で「完了」になり、次の手順が開きます（画面を再読み込みすると反映）。</div>
      <a class="small" href="https://github.com/DevSOONESSorg/line-gift-lab/blob/main/docs/course-a/{{ $st['chapter'] }}.md" target="_blank" rel="noopener">手順書（なぜ？をくわしく）</a>
    </div>
  @endif
</div>
