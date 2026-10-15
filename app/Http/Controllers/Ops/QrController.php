<?php

namespace App\Http\Controllers\Ops;

use App\Http\Controllers\Controller;
use App\Models\Location;
use App\Models\Service;
use App\Services\Ops\AnnouncementService;
use App\Services\Ops\OpsAccess;
use App\Services\Ops\QrCodeService;
use Illuminate\Http\Request;

class QrController extends Controller
{
    public function __construct(private QrCodeService $qr, private AnnouncementService $notices)
    {
    }

    /** Public landing for a scanned service QR. Read only — joining goes through the authenticated join flow. */
    public function landing(string $code)
    {
        $service = Service::where('qr_code', $code)->with(['location', 'counters'])->firstOrFail();
        $estimate = $service->estimate();
        $waiting = $service->waitingCount();
        $notices = $this->notices->publicFor($service);
        $siblings = Service::where('location_id', $service->location_id)->whereKeyNot($service->id)
            ->where('is_active', true)->orderBy('name')->get();
        $siblings->each(fn ($s) => $this->qr->codeFor($s));

        return response()
            ->view('ops.qr.landing', compact('service', 'estimate', 'waiting', 'notices', 'siblings'))
            ->header('X-Robots-Tag', 'noindex');
    }

    /** Public landing for a location QR: pick a service. */
    public function locationLanding(string $code)
    {
        $location = Location::where('qr_code', $code)->firstOrFail();
        $services = $location->services()->with('counters')->orderBy('name')->get();
        foreach ($services as $s) {
            $this->qr->codeFor($s);
        }

        return response()->view('ops.qr.location', compact('location', 'services'))->header('X-Robots-Tag', 'noindex');
    }

    /** Staff/admin: posters for assigned locations. */
    public function index(Request $request)
    {
        $locations = Location::whereIn('id', $request->user()->staffLocationIds())
            ->with(['services' => fn ($q) => $q->orderBy('name')])->orderBy('name')->get();
        foreach ($locations as $l) {
            $this->qr->codeFor($l);
            $l->services->each(fn ($s) => $this->qr->codeFor($s));
        }

        return view('ops.qr.index', compact('locations'));
    }

    public function poster(Request $request, Service $service)
    {
        abort_unless(OpsAccess::canOperate($request->user(), $service->location_id), 403);
        $service->load('location', 'counters');
        $url = $this->qr->urlFor($service);

        return view('ops.qr.poster', ['kind' => 'service', 'service' => $service, 'location' => $service->location, 'url' => $url]);
    }

    public function locationPoster(Request $request, Location $location)
    {
        abort_unless(OpsAccess::canOperate($request->user(), $location->id), 403);
        $url = $this->qr->urlFor($location);

        return view('ops.qr.poster', ['kind' => 'location', 'service' => null, 'location' => $location, 'url' => $url]);
    }

    public function rotate(Service $service)
    {
        $this->qr->rotate($service);

        return back()->with('ok', "New QR code issued for {$service->name}. Reprint its poster — the old code no longer works.");
    }

    public function rotateLocation(Location $location)
    {
        $this->qr->rotate($location);

        return back()->with('ok', "New QR code issued for {$location->name}. Reprint its poster — the old code no longer works.");
    }
}
