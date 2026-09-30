@if (session('msg'))<div class="flash">{{ session('msg') }}</div>@endif
@if ($errors->any())<div class="flash">@foreach ($errors->all() as $e)<div>{{ $e }}</div>@endforeach</div>@endif
