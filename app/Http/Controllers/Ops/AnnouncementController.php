<?php

namespace App\Http\Controllers\Ops;

use App\Http\Controllers\Controller;
use App\Http\Requests\Ops\StoreAnnouncementRequest;
use App\Models\Location;
use App\Models\Service;
use App\Models\ServiceAnnouncement;
use App\Services\Ops\AnnouncementService;
use App\Services\Ops\OpsAccess;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class AnnouncementController extends Controller
{
    public function __construct(private AnnouncementService $notices)
    {
    }

    public function index(Request $request)
    {
        $user = $request->user();
        $tab = $request->validate(['tab' => ['nullable', Rule::in(['live', 'scheduled', 'draft', 'expired'])]])['tab'] ?? 'live';
        $ids = $user->staffLocationIds();
        $now = now();

        $all = ServiceAnnouncement::whereIn('location_id', $ids)
            ->with(['location', 'service', 'counter', 'publisher', 'incident:id'])
            ->latest('id')->limit(200)->get();
        $grouped = $all->groupBy(fn ($a) => $a->displayStatus());
        $notices = $grouped->get($tab, collect());

        $locations = Location::whereIn('id', $ids)
            ->with(['services:id,location_id,name,prefix', 'counters:id,location_id,service_id,number,label,is_open'])
            ->orderBy('name')->get();
        $locationWide = $locations->mapWithKeys(fn ($l) => [$l->id => OpsAccess::canPublishLocationWide($user, $l->id)]);
        $prefill = [
            'location_id' => (int) $request->query('location', $locations->first()?->id),
            'service_id' => (int) $request->query('service', 0) ?: null,
        ];

        return view('ops.notices.index', compact('notices', 'grouped', 'tab', 'locations', 'locationWide', 'prefill'));
    }

    public function store(StoreAnnouncementRequest $request)
    {
        $a = $this->notices->create($request->user(), $request->validated());

        return redirect()->route('ops.notices.index', ['tab' => $a->displayStatus()])
            ->with('ok', $a->status === 'published' ? "Notice “{$a->title}” published." : "Draft “{$a->title}” saved.");
    }

    public function publish(Request $request, ServiceAnnouncement $announcement)
    {
        $this->authorize('manage', $announcement);
        $this->notices->publish($announcement, $request->user());

        return back()->with('ok', "Notice “{$announcement->title}” published.");
    }

    public function expire(Request $request, ServiceAnnouncement $announcement)
    {
        $this->authorize('manage', $announcement);
        $this->notices->expire($announcement, $request->user());

        return back()->with('ok', "Notice “{$announcement->title}” taken down.");
    }

    /** Polling feed: live, public-safe notices for one service (+ its location-wide notices). */
    public function feed(Request $request)
    {
        $data = $request->validate(['service' => ['required', 'integer']]);
        $service = Service::find($data['service']);
        if (! $service) {
            return response()->json(['message' => 'Service not found', 'data' => []], 404);
        }

        return response()->json([
            'message' => 'Announcements retrieved successfully',
            'data' => $this->notices->publicFor($service)->map->publicArray()->values(),
        ])->header('Cache-Control', 'no-store');
    }
}
