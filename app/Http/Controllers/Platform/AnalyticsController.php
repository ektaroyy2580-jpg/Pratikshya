<?php

namespace App\Http\Controllers\Platform;

use App\Http\Controllers\Controller;
use App\Services\Platform\PlatformAnalytics;

/** Platform-wide analytics (platform admins). All figures are local demo data. */
class AnalyticsController extends Controller
{
    public function index(PlatformAnalytics $analytics)
    {
        return view('platform.analytics', [
            'o' => $analytics->overview(),
            'modules' => $analytics->modules(),
        ]);
    }
}
