@extends('layouts.app')
@section('title', 'Explore preferences — प्रतीक्षा')

@push('head')
<link rel="stylesheet" href="{{ asset('css/modules/explore.css') }}?v={{ filemtime(public_path('css/modules/explore.css')) }}">
<style>
    .cat-opt { position: relative; }
    .cat-opt input { position: absolute; opacity: 0; width: 1px; height: 1px; }
    .cat-opt label { display: flex; align-items: center; gap: .6rem; border: 1px solid var(--pk-line); border-radius: 14px; padding: .7rem .85rem; font-weight: 700; font-size: .9rem; cursor: pointer; background: #fff; transition: border-color .2s, background .2s, box-shadow .2s; }
    .cat-opt label i.c { width: 30px; height: 30px; border-radius: 9px; display: grid; place-items: center; background: color-mix(in srgb, var(--c) 14%, #fff); color: var(--c); }
    .cat-opt label .tick { margin-left: auto; color: #c5cbe0; transition: color .2s; }
    .cat-opt input:checked + label { border-color: var(--pk-blue); background: var(--pk-tint); }
    .cat-opt input:checked + label .tick { color: var(--pk-blue); }
    .cat-opt input:focus-visible + label { box-shadow: 0 0 0 4px rgba(47, 91, 234, .18); }
    .form-switch .form-check-input { width: 3em; height: 1.6em; cursor: pointer; }
    @media (max-width: 400px) { .cat-opt label { font-size: .84rem; gap: .45rem; padding: .65rem .6rem; } }
    fieldset:disabled .cat-opt label { opacity: .55; cursor: not-allowed; }
</style>
@endpush

@section('content')
<section class="pk-page-head">
    <div class="container" style="max-width: 860px">
        <a class="pk-breadcrumb" href="{{ route('explore.index') }}"><i class="bi bi-arrow-left me-1"></i>Explore</a>
        <h1 class="h3 mt-2 mb-1">Explore preferences</h1>
        <p class="pk-muted mb-0">Choose what we suggest while you wait. Suggestions are never sponsored and never change your place in the queue.</p>
    </div>
</section>

<div class="container py-4" style="max-width: 860px">
    @if($errors->any())
        <div class="alert alert-danger"><i class="bi bi-exclamation-circle me-1"></i>{{ $errors->first() }}</div>
    @endif

    <form method="POST" action="{{ route('explore.preferences.update') }}" class="pk-card p-4 mb-4 reveal">
        @csrf
        <input type="hidden" name="suggestions_enabled" value="0">
        <div class="form-check form-switch d-flex align-items-start gap-3 ps-0 mb-1">
            <input class="form-check-input ms-0 mt-1 flex-shrink-0" type="checkbox" role="switch" id="sugOn" name="suggestions_enabled" value="1" @checked(old('suggestions_enabled', $prefs->suggestions_enabled))>
            <label class="form-check-label" for="sugOn">
                <span class="fw-bold d-block">Location-based suggestions</span>
                <span class="small pk-muted">Show nearby places that fit your remaining wait. Turn off to stop all suggestions — the map still works.</span>
            </label>
        </div>

        <fieldset id="catSet" class="mt-4" @disabled(! $prefs->suggestions_enabled)>
            <legend class="fs-6 fw-bold mb-1">Suggest these kinds of places</legend>
            <p class="small pk-muted">Hospitals and transport points are always on the map but never suggested as activities.</p>
            @php $chosen = $prefs->preferredCategories(); @endphp
            <div class="row g-2">
                @foreach($categories as $c)
                    <div class="col-6 col-md-4 cat-opt">
                        <input type="checkbox" name="categories[]" value="{{ $c['key'] }}" id="cat-{{ $c['key'] }}" @checked(in_array($c['key'], $chosen, true))>
                        <label for="cat-{{ $c['key'] }}" style="--c: {{ $c['color'] }}"><i class="bi {{ $c['icon'] }} c"></i>{{ $c['label'] }}<i class="bi bi-check-circle-fill tick"></i></label>
                    </div>
                @endforeach
            </div>
        </fieldset>

        <div class="d-flex flex-wrap gap-2 align-items-center mt-4">
            <button class="btn btn-pk"><i class="bi bi-check2 me-1"></i>Save preferences</button>
            <span class="small pk-muted">Your location is never saved — it's only used on the Explore page after you allow it.</span>
        </div>
    </form>

    <div class="pk-card p-4 reveal" style="--d:.06s">
        <h2 class="h6 mb-1">Hidden suggestions</h2>
        <p class="small pk-muted">Places you dismissed stay hidden for {{ $dismissDays }} days.</p>
        @forelse($dismissals as $d)
            <div class="d-flex align-items-center gap-3 py-2 border-bottom">
                <i class="bi bi-eye-slash pk-muted"></i>
                <div class="flex-grow-1 min-w-0">
                    <div class="fw-bold text-truncate">{{ $d->place?->name ?? 'Removed place' }}</div>
                    <div class="small pk-muted">Hidden until {{ $d->expires_at?->timezone('Asia/Kathmandu')->format('M j, g:i A') ?? 'you restore it' }}</div>
                </div>
                <form method="POST" action="{{ route('explore.undismiss', $d->id) }}">@csrf @method('DELETE')
                    <button class="btn btn-ghost btn-sm">Restore</button>
                </form>
            </div>
        @empty
            <div class="ex-empty"><i class="bi bi-check2-all d-block fs-4 mb-1"></i>Nothing hidden.</div>
        @endforelse
    </div>
</div>
@endsection

@push('scripts')
<script>
document.getElementById('sugOn')?.addEventListener('change', e => { document.getElementById('catSet').disabled = !e.target.checked; });
</script>
@endpush
