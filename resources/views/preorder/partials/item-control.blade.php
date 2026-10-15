{{-- Add / quantity stepper for a menu item. Works without JS (plain POST); preorder.js upgrades it to fetch. Needs $item, $qty. --}}
<form method="POST" action="{{ route('preorder.cart.set', $item->id) }}" class="js-item {{ $qty > 0 ? 'has-qty' : '' }}" data-item="{{ $item->id }}" data-name="{{ $item->name }}">
    @csrf
    @if(! $item->available)
        <span class="chip" style="background:#eef0f6;color:var(--pk-muted)"><i class="bi bi-slash-circle"></i>Unavailable</span>
    @else
        <button type="submit" name="qty" value="1" class="btn btn-pk btn-sm btn-add js-add" @if($qty > 0) hidden @endif aria-label="Add {{ $item->name }} to cart"><i class="bi bi-plus-lg me-1"></i>Add</button>
        <div class="stepper js-stepper" @if($qty === 0) hidden @endif>
            <button type="submit" name="qty" value="{{ max(0, $qty - 1) }}" class="js-dec" aria-label="One less {{ $item->name }}"><i class="bi bi-dash-lg"></i></button>
            <output class="js-qty" aria-live="polite">{{ $qty }}</output>
            <button type="submit" name="qty" value="{{ $qty + 1 }}" class="js-inc" aria-label="One more {{ $item->name }}" @if($qty >= config('preorder.max_qty_per_item', 20)) disabled @endif><i class="bi bi-plus-lg"></i></button>
        </div>
    @endif
</form>
