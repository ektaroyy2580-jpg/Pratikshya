<?php

namespace App\Http\Controllers\Platform;

use App\Http\Controllers\Controller;
use App\Http\Controllers\Platform\Concerns\ScopesToOrganization;
use App\Http\Requests\Platform\ServiceRequest;
use App\Models\AuditLog;
use App\Models\Location;
use App\Models\Service;

/**
 * Service (queue) configuration. Queue counters (current_number / last_number)
 * are never editable here — only QueueManager moves them.
 */
class ServiceManageController extends Controller
{
    use ScopesToOrganization;

    private const FLAGS = ['is_active', 'is_paused', 'allow_exchange', 'requires_all_documents'];

    public function create(Location $location)
    {
        $this->authorizeLocation($location->id);

        return view('platform.manage.service-form', ['location' => $location, 'service' => new Service(['counter' => 1, 'avg_minutes' => 5, 'documents' => []])]);
    }

    public function store(ServiceRequest $request, Location $location)
    {
        $this->authorizeLocation($location->id);
        $service = new Service;
        $service->forceFill($this->payload($request) + ['location_id' => $location->id, 'current_number' => 0, 'last_number' => 0])->save();
        AuditLog::record('service_created', $service, ['location_id' => $location->id]);

        return redirect()->route('platform.manage.locations.edit', $location)->with('ok', "Service “{$service->name}” created.");
    }

    public function edit(Service $service)
    {
        $this->authorizeLocation($service->location_id);

        return view('platform.manage.service-form', ['location' => $service->location, 'service' => $service]);
    }

    public function update(ServiceRequest $request, Service $service)
    {
        $this->authorizeLocation($service->location_id);
        $service->forceFill($this->payload($request));
        $changes = array_keys($service->getDirty());
        $service->save();
        AuditLog::record('service_updated', $service, ['fields' => $changes]);

        return back()->with('ok', 'Service saved.');
    }

    private function payload(ServiceRequest $request): array
    {
        $d = $request->validated();
        $out = [
            'name' => $d['name'], 'prefix' => $d['prefix'], 'counter' => (int) $d['counter'],
            'avg_minutes' => (int) $d['avg_minutes'], 'documents' => array_values($d['documents'] ?? []),
        ];
        foreach (self::FLAGS as $f) {
            $out[$f] = $request->boolean($f);
        }

        return $out;
    }
}
