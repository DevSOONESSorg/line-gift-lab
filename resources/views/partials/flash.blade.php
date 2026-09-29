@if (session('msg'))
  <div class="alert alert-success py-2">{{ session('msg') }}</div>
@endif
@if ($errors->any())
  <div class="alert alert-danger py-2">
    @foreach ($errors->all() as $e)<div>{{ $e }}</div>@endforeach
  </div>
@endif
