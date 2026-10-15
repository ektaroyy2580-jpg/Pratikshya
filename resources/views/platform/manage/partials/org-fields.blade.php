{{-- Shared organization fields. Needs $org. --}}
<div class="row g-3">
    <div class="col-md-8">
        <label class="form-label" for="org_name">Name</label>
        <input class="form-control @error('name') is-invalid @enderror" id="org_name" name="name" value="{{ old('name', $org->name) }}" required maxlength="120">
        @error('name')<div class="invalid-feedback">{{ $message }}</div>@enderror
    </div>
    <div class="col-md-4">
        <label class="form-label" for="org_type">Type</label>
        <select class="form-select" id="org_type" name="type">
            @foreach(\App\Models\Organization::TYPES as $t)
                <option value="{{ $t }}" @selected(old('type', $org->type) === $t)>{{ ucfirst($t) }}</option>
            @endforeach
        </select>
    </div>
    <div class="col-md-4">
        <label class="form-label" for="org_email">Contact email</label>
        <input class="form-control @error('contact_email') is-invalid @enderror" id="org_email" type="email" name="contact_email" value="{{ old('contact_email', $org->contact_email) }}" maxlength="120">
        @error('contact_email')<div class="invalid-feedback">{{ $message }}</div>@enderror
    </div>
    <div class="col-md-4">
        <label class="form-label" for="org_phone">Contact phone</label>
        <input class="form-control @error('contact_phone') is-invalid @enderror" id="org_phone" name="contact_phone" value="{{ old('contact_phone', $org->contact_phone) }}" maxlength="30">
        @error('contact_phone')<div class="invalid-feedback">{{ $message }}</div>@enderror
    </div>
    <div class="col-md-4">
        <label class="form-label" for="org_web">Website</label>
        <input class="form-control @error('website') is-invalid @enderror" id="org_web" type="url" name="website" value="{{ old('website', $org->website) }}" placeholder="https://…" maxlength="200">
        @error('website')<div class="invalid-feedback">{{ $message }}</div>@enderror
    </div>
    <div class="col-12">
        <div class="pf-switch">
            <div><div class="t">Allow voluntary queue exchange</div><div class="d">When off, users at this organization's locations cannot request token swaps. Exchanges never let anyone pay for priority.</div></div>
            <div class="form-check form-switch">
                <input type="hidden" name="allow_queue_exchange" value="0">
                <input class="form-check-input" type="checkbox" role="switch" name="allow_queue_exchange" value="1" aria-label="Allow voluntary queue exchange" @checked(old('allow_queue_exchange', $org->setting('allow_queue_exchange', true)))>
            </div>
        </div>
    </div>
</div>
