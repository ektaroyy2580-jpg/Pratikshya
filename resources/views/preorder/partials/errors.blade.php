@if($errors->any())
    <div class="alert alert-danger d-flex gap-2 align-items-start fade-up" role="alert">
        <i class="bi bi-exclamation-octagon-fill mt-1"></i>
        <div>@foreach($errors->all() as $e)<div class="fw-semibold">{{ $e }}</div>@endforeach</div>
    </div>
@endif
