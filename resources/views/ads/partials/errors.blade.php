@if($errors->any() && ! $errors->has('queue'))
    <div class="alert alert-danger d-flex gap-2 align-items-start fade-up" role="alert">
        <i class="bi bi-exclamation-octagon-fill mt-1"></i>
        <div>
            @if($errors->count() === 1)
                {{ $errors->first() }}
            @else
                <strong>Please fix the following:</strong>
                <ul class="mb-0 ps-3 small">@foreach($errors->all() as $e)<li>{{ $e }}</li>@endforeach</ul>
            @endif
        </div>
    </div>
@endif
