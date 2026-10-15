@if($errors->any())
    <div class="bl-err d-flex gap-2 align-items-start mb-3" role="alert">
        <i class="bi bi-exclamation-octagon-fill mt-1"></i>
        <div>
            @foreach(collect($errors->all())->unique()->take(4) as $e)
                <div>{{ $e }}</div>
            @endforeach
        </div>
    </div>
@endif
