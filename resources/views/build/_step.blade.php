@php $accounts = \App\Services\BuildGuide::ACCOUNTS; @endphp
<div class="bstep bstep-{{ $st['state'] }}">
  <div class="bstep-head">
    <span class="bno">@if ($st['state'] === 'done')<i class="bi bi-check-lg"></i>@else{{ $st['no'] }}@endif</span>
    <span class="flex-grow-1"><b>{{ $st['title'] }}</b>
      <span class="bwho">{{ $accounts[$st['who']] }}@isset($st['who2']) → {{ $accounts[$st['who2']] }}@endisset</span></span>
  </div>
  @if ($open ?? false)
    <div class="bstep-body">
      @if (! empty($st['bad']))<div class="alert alert-danger py-1 small mb-2">{{ $st['bad'] }}</div>@endif
      <ol class="small mb-2">@foreach ($st['todo'] as $t)<li><span class="user-select-all">{{ $t }}</span></li>@endforeach</ol>
      <p class="small bwhy mb-2"><i class="bi bi-lightbulb"></i> {{ $st['why'] }}</p>
      <div class="d-flex flex-wrap gap-2">
        @if ($st['link'])<a class="btn btn-sm btn-primary" href="{{ $st['link'] }}">{{ $st['linkLabel'] }} →</a>@endif
        <a class="btn btn-sm btn-outline-secondary" href="https://github.com/DevSOONESSorg/line-gift-lab/blob/main/docs/course-a/{{ $st['chapter'] }}.md" target="_blank" rel="noopener">手順書</a>
        @isset($st['doc'])<a class="btn btn-sm btn-outline-secondary" href="{{ $st['doc'] }}" target="_blank" rel="noopener">LINE公式ドキュメント</a>@endisset
      </div>
    </div>
  @endif
</div>
