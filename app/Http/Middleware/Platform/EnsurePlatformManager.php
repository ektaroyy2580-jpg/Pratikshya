<?php

namespace App\Http\Middleware\Platform;

use App\Services\Platform\OrgAccess;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/** Allows platform admins and organization admins. Per-record scoping is still checked in controllers. */
class EnsurePlatformManager
{
    public function __construct(private OrgAccess $access)
    {
    }

    public function handle(Request $request, Closure $next): Response
    {
        abort_unless($this->access->isManager($request->user()), 403, 'You do not have permission to access the management console.');

        return $next($request);
    }
}
