{{-- $p: BillParticipant, $size: '' | 'sm' | 'lg' --}}
<span class="bl-av {{ $size ?? '' }}" style="--h: {{ (($p->user_id ?? $p->id) * 47 + 200) % 360 }}" title="{{ $p->display_name }}" aria-hidden="true">{{ $p->initials() }}</span>
