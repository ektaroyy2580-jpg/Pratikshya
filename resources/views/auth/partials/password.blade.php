@php
    $name = $name ?? 'password';
    $label = $label ?? 'Password';
    $autocomplete = $autocomplete ?? 'current-password';
    $strength = $strength ?? false;
    $required = $required ?? true;
    $minlength = $minlength ?? ($strength ? 8 : null);
    $matches = $matches ?? null; // optional: id of the field this one must match (confirmation)
    $help = $help ?? null;
    $aside = $aside ?? null;     // optional: html shown at the right of the label (e.g. "Forgot password?")
    $fieldId = $id ?? ('f-' . str_replace(['_', '[', ']'], '-', $name));
    $describedBy = trim(
        ($errors->has($name) ? $fieldId . '-error ' : '')
        . ($strength ? $fieldId . '-strength ' : '')
        . ($matches ? $fieldId . '-match ' : '')
        . ($help ? $fieldId . '-help' : '')
    );
@endphp
<div class="mb-3" data-pw-field>
    <div class="label-row">
        <label for="{{ $fieldId }}" class="form-label">{{ $label }}</label>
        @if($aside){!! $aside !!}@endif
    </div>
    <div class="pw-wrap">
        <input
            type="password"
            id="{{ $fieldId }}"
            name="{{ $name }}"
            class="form-control @error($name) is-invalid @enderror"
            autocomplete="{{ $autocomplete }}"
            @if($required) required @endif
            @if($minlength) minlength="{{ $minlength }}" @endif
            @if($strength) data-pw-strength @endif
            @if($matches) data-pw-match="{{ $matches }}" @endif
            @if($describedBy !== '') aria-describedby="{{ $describedBy }}" @endif
            @error($name) aria-invalid="true" @enderror
        >
        <button type="button" class="btn-pw" data-pw-toggle="{{ $fieldId }}"
                aria-controls="{{ $fieldId }}" aria-label="Show password" aria-pressed="false">
            <i class="bi bi-eye" aria-hidden="true"></i>
        </button>
    </div>
    @error($name)
        <div class="invalid-feedback d-block" id="{{ $fieldId }}-error">{{ $message }}</div>
    @enderror

    @if($help)
        <div class="form-text" id="{{ $fieldId }}-help">{{ $help }}</div>
    @endif

    @if($strength)
        <div id="{{ $fieldId }}-strength" class="mt-2" data-pw-meter-for="{{ $fieldId }}">
            <div class="d-flex align-items-center gap-3">
                <div class="pw-meter flex-grow-1" data-score="0" aria-hidden="true">
                    <span></span><span></span><span></span><span></span>
                </div>
                <span class="pw-label" aria-live="polite">Strength: <span data-pw-label>—</span></span>
            </div>
            <ul class="pw-checks" aria-label="Password requirements">
                <li data-rule="length"><i class="bi bi-circle" aria-hidden="true"></i> 8+ characters</li>
                <li data-rule="letter"><i class="bi bi-circle" aria-hidden="true"></i> A letter</li>
                <li data-rule="number"><i class="bi bi-circle" aria-hidden="true"></i> A number</li>
                <li data-rule="mix"><i class="bi bi-circle" aria-hidden="true"></i> Upper + lower or symbol</li>
            </ul>
        </div>
    @endif

    @if($matches)
        <div class="match-hint" id="{{ $fieldId }}-match" aria-live="polite"></div>
    @endif
</div>
