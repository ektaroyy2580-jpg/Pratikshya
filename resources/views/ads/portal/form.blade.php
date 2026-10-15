@extends('layouts.app')
@section('title', ($campaign->exists ? 'Edit campaign' : 'New campaign').' — प्रतीक्षा')

@push('head')
<link href="{{ asset('css/modules/ads.css') }}?v={{ @filemtime(public_path('css/modules/ads.css')) }}" rel="stylesheet">
@endpush

@section('content')
@php
    $editing = $campaign->exists;
    $selPlacements = old('placements', $campaign->placements ?? []);
    $theme = old('creative_theme', $campaign->creative_theme ?? 'blue');
    [$pa, $pb] = $themes[$theme] ?? $themes['blue'];
    $firstAdv = $editing ? $campaign->advertiser : $advertisers->first();
    $advCats = $advertisers->mapWithKeys(fn ($a) => [$a->id => $a->category]);
@endphp
<header class="pk-page-head">
    <div class="container">
        <a href="{{ $editing ? route('ads.campaigns.show', $campaign) : route('ads.portal') }}" class="pk-breadcrumb"><i class="bi bi-arrow-left me-1"></i>{{ $editing ? $campaign->name : 'Advertiser portal' }}</a>
        <h1 class="h2 mb-1 mt-2">{{ $editing ? 'Edit campaign' : 'New campaign' }}</h1>
        <p class="pk-muted mb-0">Every campaign is reviewed by an admin before it runs.@if($editing && in_array($campaign->status, ['active', 'paused'])) <strong>Saving changes takes this campaign out of rotation until it is re-approved.</strong>@endif</p>
    </div>
</header>

<div class="container py-4 py-md-5">
    @include('ads.partials.errors')
    <form method="post" action="{{ $editing ? route('ads.campaigns.update', $campaign) : route('ads.campaigns.store') }}" enctype="multipart/form-data" id="campaignForm" novalidate>
        @csrf
        @if($editing) @method('PUT') @endif
        <div class="row g-4">
            <div class="col-lg-7 d-flex flex-column gap-4">
                {{-- Basics --}}
                <section class="pk-card p-3 p-md-4 reveal">
                    <h2 class="h5 mb-3"><i class="bi bi-1-circle me-2 text-primary"></i>Basics</h2>
                    <div class="row g-3">
                        @if(! $editing)
                            <div class="col-md-6">
                                <label class="form-label" for="advertiser_id">Business</label>
                                <select class="form-select @error('advertiser_id') is-invalid @enderror" name="advertiser_id" id="advertiser_id" required>
                                    @foreach($advertisers as $a)<option value="{{ $a->id }}" data-cat="{{ $a->category }}" data-name="{{ $a->name }}" @selected((int) old('advertiser_id', $firstAdv?->id) === $a->id)>{{ $a->name }}{{ $a->status !== 'approved' ? ' ('.$a->status.')' : '' }}</option>@endforeach
                                </select>
                                @error('advertiser_id')<div class="invalid-feedback">{{ $message }}</div>@enderror
                            </div>
                        @endif
                        <div class="{{ $editing ? 'col-12' : 'col-md-6' }}">
                            <label class="form-label" for="name">Campaign name <span class="pk-muted fw-normal">(internal)</span></label>
                            <input class="form-control @error('name') is-invalid @enderror" id="name" name="name" value="{{ old('name', $campaign->name) }}" maxlength="120" required>
                            @error('name')<div class="invalid-feedback">{{ $message }}</div>@enderror
                        </div>
                    </div>
                </section>

                {{-- Creative --}}
                <section class="pk-card p-3 p-md-4 reveal">
                    <h2 class="h5 mb-3"><i class="bi bi-2-circle me-2 text-primary"></i>Creative</h2>
                    <div class="row g-3">
                        <div class="col-12">
                            <label class="form-label d-flex justify-content-between" for="headline">Headline <span class="pk-muted fw-normal small" data-count="headline">0/80</span></label>
                            <input class="form-control @error('headline') is-invalid @enderror" id="headline" name="headline" value="{{ old('headline', $campaign->headline) }}" maxlength="80" required data-preview="headline">
                            @error('headline')<div class="invalid-feedback">{{ $message }}</div>@enderror
                        </div>
                        <div class="col-12">
                            <label class="form-label d-flex justify-content-between" for="body">Short description <span class="pk-muted fw-normal small" data-count="body">0/200</span></label>
                            <textarea class="form-control @error('body') is-invalid @enderror" id="body" name="body" rows="2" maxlength="200" data-preview="body">{{ old('body', $campaign->body) }}</textarea>
                            @error('body')<div class="invalid-feedback">{{ $message }}</div>@enderror
                        </div>
                        <div class="col-md-5">
                            <label class="form-label" for="cta_label">Button text</label>
                            <input class="form-control @error('cta_label') is-invalid @enderror" id="cta_label" name="cta_label" value="{{ old('cta_label', $campaign->cta_label) }}" maxlength="30" required data-preview="cta">
                            @error('cta_label')<div class="invalid-feedback">{{ $message }}</div>@enderror
                        </div>
                        <div class="col-md-7">
                            <label class="form-label" for="cta_url">Link</label>
                            <input class="form-control @error('cta_url') is-invalid @enderror" id="cta_url" name="cta_url" value="{{ old('cta_url', $campaign->cta_url) }}" maxlength="255" placeholder="/find  or  https://example.com.np" required>
                            <div class="form-text">An app page starting with <code>/</code> or a secure <code>https://</code> address.</div>
                            @error('cta_url')<div class="invalid-feedback">{{ $message }}</div>@enderror
                        </div>
                        <div class="col-12">
                            <span class="form-label d-block">Generated design</span>
                            <div class="d-flex flex-wrap gap-2" role="radiogroup" aria-label="Creative colour theme">
                                @foreach($themes as $k => [$a, $b])
                                    <label class="theme-swatch" title="{{ ucfirst($k) }}">
                                        <input type="radio" name="creative_theme" value="{{ $k }}" @checked($theme === $k) data-a="{{ $a }}" data-b="{{ $b }}">
                                        <span style="--ad-a: {{ $a }}; --ad-b: {{ $b }}"></span>
                                        <span class="visually-hidden">{{ ucfirst($k) }}</span>
                                    </label>
                                @endforeach
                            </div>
                        </div>
                        <div class="col-12">
                            <label class="form-label" for="creative_image">Or upload an image <span class="pk-muted fw-normal">(JPG, PNG or WebP, max 1 MB)</span></label>
                            <input type="file" class="form-control @error('creative_image') is-invalid @enderror" id="creative_image" name="creative_image" accept="image/jpeg,image/png,image/webp">
                            @error('creative_image')<div class="invalid-feedback">{{ $message }}</div>@enderror
                            <div class="small text-danger mt-1 d-none" id="imgWarn" role="alert"></div>
                            @if($editing && $campaign->creative_image)
                                <div class="form-check mt-2">
                                    <input class="form-check-input" type="checkbox" value="1" id="remove_image" name="remove_image">
                                    <label class="form-check-label small" for="remove_image">Remove the current image and use the generated design</label>
                                </div>
                            @endif
                        </div>
                    </div>
                </section>

                {{-- Targeting --}}
                <section class="pk-card p-3 p-md-4 reveal">
                    <h2 class="h5 mb-1"><i class="bi bi-3-circle me-2 text-primary"></i>Where it appears</h2>
                    <p class="small pk-muted">Targeting uses page context only — never people's personal data or activity.</p>
                    <div class="row g-2 mb-3">
                        @foreach($placements as $k => $label)
                            <div class="col-sm-6">
                                <label class="place-check"><input class="form-check-input m-0" type="checkbox" name="placements[]" value="{{ $k }}" @checked(in_array($k, $selPlacements, true))>{{ $label }}</label>
                            </div>
                        @endforeach
                    </div>
                    @error('placements')<div class="small text-danger mb-2">{{ $message }}</div>@enderror
                    @error('placements.*')<div class="small text-danger mb-2">{{ $message }}</div>@enderror
                    <div class="row g-3">
                        <div class="col-md-6">
                            <label class="form-label" for="target_location_id">Location <span class="pk-muted fw-normal">(optional)</span></label>
                            <select class="form-select @error('target_location_id') is-invalid @enderror" id="target_location_id" name="target_location_id">
                                <option value="">All locations</option>
                                @foreach($locations as $l)<option value="{{ $l->id }}" @selected((int) old('target_location_id', $campaign->target_location_id) === $l->id)>{{ $l->name }}</option>@endforeach
                            </select>
                            @error('target_location_id')<div class="invalid-feedback">{{ $message }}</div>@enderror
                        </div>
                        <div class="col-md-6">
                            <label class="form-label" for="target_category">Place category <span class="pk-muted fw-normal">(optional)</span></label>
                            <select class="form-select @error('target_category') is-invalid @enderror" id="target_category" name="target_category">
                                <option value="">Any</option>
                                @foreach($categories as $k => $label)<option value="{{ $k }}" @selected(old('target_category', $campaign->target_category) === $k)>{{ $label }}</option>@endforeach
                            </select>
                            @error('target_category')<div class="invalid-feedback">{{ $message }}</div>@enderror
                        </div>
                        <div class="col-12">
                            <div class="form-check form-switch">
                                <input class="form-check-input" type="checkbox" role="switch" id="allows_rewarded" name="allows_rewarded" value="1" @checked(old('allows_rewarded', $campaign->allows_rewarded))>
                                <label class="form-check-label" for="allows_rewarded">Offer as an optional rewarded video (game hub &amp; post-game)</label>
                            </div>
                        </div>
                        <div class="col-12 {{ ($editing ? $campaign->is_healthcare : false) || in_array(old('target_category', $campaign->target_category), $healthcareCategories, true) || in_array($firstAdv?->category, $healthcareCategories, true) ? '' : 'd-none' }}" id="hcBox">
                            <label class="form-label" for="healthcare_disclosure"><i class="bi bi-heart-pulse me-1 text-danger"></i>Healthcare disclosure <span class="text-danger">*</span></label>
                            <input class="form-control @error('healthcare_disclosure') is-invalid @enderror" id="healthcare_disclosure" name="healthcare_disclosure" maxlength="255" value="{{ old('healthcare_disclosure', $campaign->healthcare_disclosure) }}" placeholder="Paid promotion by a licensed pharmacy. Not medical advice — consult a doctor." data-preview="disclosure">
                            <div class="form-text">Required for pharmacies and hospitals. Healthcare ads cannot run on queue pages.</div>
                            @error('healthcare_disclosure')<div class="invalid-feedback">{{ $message }}</div>@enderror
                        </div>
                    </div>
                </section>

                {{-- Schedule & budget --}}
                <section class="pk-card p-3 p-md-4 reveal">
                    <h2 class="h5 mb-3"><i class="bi bi-4-circle me-2 text-primary"></i>Schedule &amp; budget (NPR)</h2>
                    <div class="row g-3">
                        <div class="col-6">
                            <label class="form-label" for="starts_on">Starts</label>
                            <input type="date" class="form-control @error('starts_on') is-invalid @enderror" id="starts_on" name="starts_on" value="{{ old('starts_on', $campaign->starts_on?->toDateString()) }}" required>
                            @error('starts_on')<div class="invalid-feedback">{{ $message }}</div>@enderror
                        </div>
                        <div class="col-6">
                            <label class="form-label" for="ends_on">Ends</label>
                            <input type="date" class="form-control @error('ends_on') is-invalid @enderror" id="ends_on" name="ends_on" value="{{ old('ends_on', $campaign->ends_on?->toDateString()) }}" required>
                            @error('ends_on')<div class="invalid-feedback">{{ $message }}</div>@enderror
                        </div>
                        @foreach(['budget_total' => 'Total budget', 'daily_budget' => 'Daily budget', 'cpm_rate' => 'CPM (per 1,000 views)', 'cpc_rate' => 'CPC (per click)'] as $f => $label)
                            <div class="col-6 col-md-3">
                                <label class="form-label small" for="{{ $f }}">{{ $label }}</label>
                                <input type="number" step="0.01" min="0" inputmode="decimal" class="form-control @error($f) is-invalid @enderror" id="{{ $f }}" name="{{ $f }}" value="{{ old($f, $campaign->{$f}) }}" required>
                                @error($f)<div class="invalid-feedback">{{ $message }}</div>@enderror
                            </div>
                        @endforeach
                    </div>
                    <p class="small pk-muted mt-3 mb-0"><i class="bi bi-info-circle me-1"></i>Delivery stops automatically when the estimated spend reaches the daily or total budget. This demo build does not charge anyone.</p>
                </section>
            </div>

            {{-- Live preview --}}
            <div class="col-lg-5">
                <div class="ads-sticky d-flex flex-column gap-3">
                    <div class="pk-card p-3 p-md-4 reveal">
                        <div class="d-flex justify-content-between align-items-center mb-3">
                            <h2 class="h6 mb-0">Live preview</h2>
                            <span class="small pk-muted">How people will see it</span>
                        </div>
                        <aside class="ad-slot" aria-label="Preview of your sponsored card">
                            <div class="ad-card">
                                <div class="ad-media {{ $campaign->imageUrl() ? 'has-img' : '' }}" id="pvMedia" style="--ad-a: {{ $pa }}; --ad-b: {{ $pb }};" aria-hidden="true">
                                    <img id="pvImg" src="{{ $campaign->imageUrl() }}" alt="" class="{{ $campaign->imageUrl() ? '' : 'd-none' }}">
                                    <span class="ad-glyph"><i class="bi {{ $campaign->exists ? $campaign->categoryIcon() : 'bi-shop' }}"></i></span>
                                    <span class="ad-mark" id="pvMark">{{ $firstAdv?->name }}</span>
                                </div>
                                <div class="ad-body">
                                    <div class="ad-meta"><span class="ad-label"><i class="bi bi-megaphone-fill"></i>Sponsored</span><span class="ad-advertiser" id="pvAdv">{{ $firstAdv?->name }}</span></div>
                                    <h3 class="ad-headline" id="pvHeadline">{{ $campaign->headline ?: 'Your headline' }}</h3>
                                    <p class="ad-text" id="pvBody">{{ $campaign->body }}</p>
                                    <p class="ad-disclosure d-none" id="pvDisc"><i class="bi bi-info-circle-fill"></i><span><strong>Healthcare ad.</strong> <span id="pvDiscText"></span></span></p>
                                    <div class="ad-actions"><span class="ad-cta"><span id="pvCta">{{ $campaign->cta_label ?: 'Learn more' }}</span> <i class="bi bi-arrow-right"></i></span></div>
                                </div>
                            </div>
                        </aside>
                    </div>
                    <div class="pk-card p-3 p-md-4 reveal" style="--d:.06s">
                        <div class="d-grid gap-2">
                            <button class="btn btn-pk" name="action" value="submit"><i class="bi bi-send-check me-1"></i>Save &amp; submit for review</button>
                            <button class="btn btn-ghost" name="action" value="draft"><i class="bi bi-save me-1"></i>Save as draft</button>
                        </div>
                        @if($firstAdv && ! $firstAdv->isApproved())
                            <p class="small text-warning-emphasis mt-2 mb-0"><i class="bi bi-hourglass-split me-1"></i>Your business is pending approval: campaigns are saved as drafts until then.</p>
                        @endif
                    </div>
                    @include('ads.partials.policy')
                </div>
            </div>
        </div>
    </form>
</div>
@endsection

@push('scripts')
<script>
(() => {
    const f = document.getElementById('campaignForm');
    const $ = id => document.getElementById(id);
    const hc = @json($healthcareCategories);
    const fixedCat = @json($editing ? $campaign->advertiser?->category : null);
    const bind = (input, target, fallback) => {
        const el = f.querySelector(`[data-preview="${input}"]`);
        if (!el) return;
        const update = () => { target.textContent = el.value.trim() || fallback; const c = f.querySelector(`[data-count="${el.name}"]`); if (c) c.textContent = el.value.length + '/' + el.maxLength; };
        el.addEventListener('input', update); update();
    };
    bind('headline', $('pvHeadline'), 'Your headline');
    bind('body', $('pvBody'), '');
    bind('cta', $('pvCta'), 'Learn more');
    bind('disclosure', $('pvDiscText'), 'Paid promotion. Not medical advice.');

    f.querySelectorAll('input[name="creative_theme"]').forEach(r => r.addEventListener('change', () => {
        $('pvMedia').style.setProperty('--ad-a', r.dataset.a); $('pvMedia').style.setProperty('--ad-b', r.dataset.b);
    }));

    const adv = $('advertiser_id');
    const syncHc = () => {
        const opt = adv?.selectedOptions[0];
        const cat = $('target_category').value || (opt ? opt.dataset.cat : fixedCat);
        const advCat = opt ? opt.dataset.cat : fixedCat;
        const on = hc.includes(cat) || hc.includes(advCat);
        $('hcBox').classList.toggle('d-none', !on);
        $('pvDisc').classList.toggle('d-none', !on);
        if (opt) { $('pvAdv').textContent = opt.dataset.name; $('pvMark').textContent = opt.dataset.name; }
    };
    adv?.addEventListener('change', syncHc);
    $('target_category').addEventListener('change', syncHc);
    syncHc();

    const file = $('creative_image');
    file.addEventListener('change', () => {
        const warn = $('imgWarn');
        warn.classList.add('d-none');
        const fl = file.files[0];
        if (!fl) return;
        if (!['image/jpeg', 'image/png', 'image/webp'].includes(fl.type) || fl.size > 1024 * 1024) {
            warn.textContent = 'Use a JPG, PNG or WebP image up to 1 MB.'; warn.classList.remove('d-none'); file.value = ''; return;
        }
        const url = URL.createObjectURL(fl);
        $('pvImg').src = url; $('pvImg').classList.remove('d-none'); $('pvMedia').classList.add('has-img');
    });
    $('remove_image')?.addEventListener('change', e => { $('pvImg').classList.toggle('d-none', e.target.checked); $('pvMedia').classList.toggle('has-img', !e.target.checked); });
})();
</script>
@endpush
