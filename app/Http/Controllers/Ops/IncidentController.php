<?php

namespace App\Http\Controllers\Ops;

use App\Http\Controllers\Controller;
use App\Http\Requests\Ops\PublishIncidentNoticeRequest;
use App\Http\Requests\Ops\StoreIncidentRequest;
use App\Http\Requests\Ops\UpdateIncidentRequest;
use App\Models\Location;
use App\Models\OperationalIncident;
use App\Services\Ops\AnnouncementService;
use App\Services\Ops\IncidentService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\Rule;

class IncidentController extends Controller
{
    public function __construct(private IncidentService $incidents, private AnnouncementService $notices)
    {
    }

    public function index(Request $request)
    {
        $user = $request->user();
        $filters = $request->validate([
            'status' => ['nullable', Rule::in(['active', 'resolved', 'all'])],
            'location' => ['nullable', 'integer'],
            'priority' => ['nullable', Rule::in(OperationalIncident::PRIORITIES)],
        ]);
        $status = $filters['status'] ?? 'active';

        $base = OperationalIncident::visibleTo($user);
        $incidents = (clone $base)
            ->with(['location', 'counter', 'service', 'reporter'])
            ->when($status === 'active', fn ($q) => $q->unresolved())
            ->when($status === 'resolved', fn ($q) => $q->whereIn('status', ['resolved', 'closed']))
            ->when($filters['location'] ?? null, fn ($q, $l) => $q->where('location_id', $l))
            ->when($filters['priority'] ?? null, fn ($q, $p) => $q->where('priority', $p))
            ->orderByRaw("CASE status WHEN 'open' THEN 0 WHEN 'acknowledged' THEN 1 WHEN 'in_progress' THEN 2 ELSE 3 END")
            ->orderByRaw("CASE priority WHEN 'critical' THEN 0 WHEN 'high' THEN 1 WHEN 'medium' THEN 2 ELSE 3 END")
            ->latest('reported_at')
            ->paginate(15)->withQueryString();

        $counts = [
            'active' => (clone $base)->unresolved()->count(),
            'critical' => (clone $base)->unresolved()->whereIn('priority', ['critical', 'high'])->count(),
            'resolved_today' => (clone $base)->whereIn('status', ['resolved', 'closed'])->where('resolved_at', '>=', today())->count(),
        ];
        $locations = Location::whereIn('id', $user->staffLocationIds())->orderBy('name')->get(['id', 'name']);

        return view('ops.incidents.index', compact('incidents', 'counts', 'locations', 'filters', 'status'));
    }

    public function create(Request $request)
    {
        $locations = Location::whereIn('id', $request->user()->staffLocationIds())
            ->with(['services:id,location_id,name,prefix', 'counters:id,location_id,service_id,number,label,is_open'])
            ->orderBy('name')->get();
        abort_if($locations->isEmpty(), 403, 'You have no assigned locations yet.');

        $prefill = [
            'location_id' => (int) $request->query('location', $locations->first()->id),
            'service_id' => (int) $request->query('service', 0) ?: null,
            'counter_id' => (int) $request->query('counter', 0) ?: null,
            'category' => array_key_exists($request->query('category'), OperationalIncident::CATEGORIES) ? $request->query('category') : null,
        ];

        return view('ops.incidents.create', compact('locations', 'prefill'));
    }

    public function store(StoreIncidentRequest $request)
    {
        $incident = $this->incidents->report($request->user(), $request->validated(), $request->file('photo'));

        return redirect()->route('ops.incidents.show', $incident)->with('ok', "Incident {$incident->reference()} reported.");
    }

    public function show(OperationalIncident $incident)
    {
        $this->authorize('view', $incident);
        $incident->load(['location', 'counter', 'service', 'reporter', 'updates.author', 'announcements']);
        $suggestion = $incident->isConfidential() ? null : $this->notices->suggestionFor($incident);
        $canLocationWide = \App\Services\Ops\OpsAccess::canPublishLocationWide(request()->user(), $incident->location_id);

        return view('ops.incidents.show', compact('incident', 'suggestion', 'canLocationWide'));
    }

    public function update(UpdateIncidentRequest $request, OperationalIncident $incident)
    {
        $this->incidents->update($incident, $request->user(), $request->validated());

        return redirect()->route('ops.incidents.show', $incident)->with('ok', 'Incident updated.');
    }

    public function publish(PublishIncidentNoticeRequest $request, OperationalIncident $incident)
    {
        $a = $this->notices->publishFromIncident($incident, $request->user(), $request->validated());

        return redirect()->route('ops.incidents.show', $incident)->with('ok', "Public notice “{$a->title}” is live.");
    }

    /** Private photo: streamed only to operators of the incident's location. */
    public function photo(OperationalIncident $incident)
    {
        $this->authorize('view', $incident);
        $disk = Storage::disk(IncidentService::PHOTO_DISK);
        abort_unless($incident->photo_path && $disk->exists($incident->photo_path), 404);

        return $disk->response($incident->photo_path, null, [
            'Cache-Control' => 'private, no-store',
            'X-Content-Type-Options' => 'nosniff',
            'Content-Security-Policy' => "default-src 'none'",
        ]);
    }
}
