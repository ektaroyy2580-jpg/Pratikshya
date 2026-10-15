<?php

namespace App\Http\Controllers\Ops;

use App\Http\Controllers\Controller;
use App\Models\Location;
use App\Models\OperationalIncident;
use App\Models\ServiceAnnouncement;

/** Admin analytics: incidents by status / priority / category / location, notice activity. */
class OpsOverviewController extends Controller
{
    public function __invoke()
    {
        $byStatus = OperationalIncident::selectRaw('status, count(*) c')->groupBy('status')->pluck('c', 'status');
        $byPriority = OperationalIncident::unresolved()->selectRaw('priority, count(*) c')->groupBy('priority')->pluck('c', 'priority');
        $byCategory = OperationalIncident::selectRaw('category, count(*) c')->groupBy('category')->orderByDesc('c')->pluck('c', 'category');
        $byLocation = OperationalIncident::unresolved()->selectRaw('location_id, count(*) c')->groupBy('location_id')->pluck('c', 'location_id');
        $locations = Location::orderBy('name')->get(['id', 'name', 'area']);

        $resolved = OperationalIncident::whereNotNull('resolved_at')->latest('resolved_at')->limit(100)->get(['reported_at', 'resolved_at']);
        $mttr = $resolved->count()
            ? (int) round($resolved->avg(fn ($i) => max(0, $i->reported_at->diffInMinutes($i->resolved_at))))
            : null;

        $notices = [
            'live' => ServiceAnnouncement::live()->count(),
            'week' => ServiceAnnouncement::where('status', '!=', 'draft')->where('created_at', '>=', now()->subDays(7))->count(),
            'drafts' => ServiceAnnouncement::where('status', 'draft')->count(),
        ];
        $recent = OperationalIncident::with(['location', 'counter'])->latest('reported_at')->limit(8)->get();
        $total = (int) $byStatus->sum();

        return view('ops.admin.overview', compact('byStatus', 'byPriority', 'byCategory', 'byLocation', 'locations', 'mttr', 'notices', 'recent', 'total'));
    }
}
