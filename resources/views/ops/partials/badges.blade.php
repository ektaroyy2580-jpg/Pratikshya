{{-- Status + priority + internal badges for an incident. Param: $incident --}}
<span class="ist ist-{{ $incident->status }}">{{ $incident->statusLabel() }}</span>
<span class="prio prio-{{ $incident->priority }}">{{ $incident->priority }}</span>
@if($incident->isConfidential())
    <span class="internal-tag" title="Never shown to the public"><i class="bi bi-lock-fill"></i> Internal</span>
@endif
