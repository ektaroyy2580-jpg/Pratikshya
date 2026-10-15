{{-- "Request exchange" form for one of the signed-in user's waiting tickets. Needs $ticket (with service). --}}
@php $svc = $ticket->service; $fid = 'igTarget'.$ticket->id; @endphp
@if(! $svc->allow_exchange)
    <div class="ig-note d-flex gap-2 align-items-start"><i class="bi bi-slash-circle mt-1"></i><span>This office does not allow queue exchanges for {{ $svc->name }}.</span></div>
@else
    <form method="POST" action="{{ route('integrity.exchange.store', $ticket) }}" data-ig-once novalidate>
        @csrf
        <label class="form-label small mb-1" for="{{ $fid }}">Swap with which token?</label>
        <div class="d-flex flex-wrap gap-2">
            <div class="input-group ig-token-input flex-grow-1" style="max-width: 260px">
                <span class="input-group-text">{{ $svc->prefix }}-</span>
                <input id="{{ $fid }}" name="target_token" class="form-control @error('target_token') is-invalid @enderror" inputmode="numeric"
                       maxlength="12" placeholder="e.g. 29" autocomplete="off" data-ig-token required
                       value="{{ old('target_token') }}" aria-describedby="{{ $fid }}Help">
            </div>
            <button type="submit" class="btn btn-pk btn-sm px-3" data-busy="Sending…"><i class="bi bi-send me-1"></i>Send request</button>
        </div>
        @error('target_token')<div class="small text-danger mt-1">{{ $message }}</div>@enderror
        <div id="{{ $fid }}Help" class="ig-note mt-2">Same service only. They see your token, never your name. Nothing is paid or traded{{ $svc->requires_exchange_approval ? ', and staff approve before positions change' : '' }}.</div>
    </form>
@endif
