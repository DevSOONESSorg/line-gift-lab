{{-- 公式アカウントのアイコン（運営は黒×金、お店は名前から決めた色） --}}
@php
  $palette = ['#7c5c3b', '#2f6f5e', '#6b4f8a', '#9a3b3b', '#2d5d8a', '#8a6d1f'];
  $bg = $oa->is_platform ? '#0c0c0c' : $palette[crc32($oa->name) % count($palette)];
@endphp
<span class="av {{ $oa->is_platform ? 'av-platform' : '' }} {{ $size ?? '' }}" style="background:{{ $bg }}">@if ($oa->is_platform)<i class="bi bi-cup-straw"></i>@else{{ mb_substr($oa->name, 0, 1) }}@endif</span>
