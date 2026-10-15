<?php

namespace App\Http\Controllers\Platform;

use App\Http\Controllers\Controller;
use App\Models\Ticket;
use App\Services\Platform\NotificationPresenter;
use App\Services\Platform\OrgAccess;
use App\Services\Rewards\RewardService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;

/** Personal home: live tokens, shortcuts into other modules (only when installed), notifications, points. */
class DashboardController extends Controller
{
    /** Optional partials from other modules: [view, label]. Rendered only if the view exists. */
    public const MODULE_PARTIALS = [
        'library.partials.continue-card',
        'english.partials.ticket-widget',
        'games.partials.ticket-widget',
        'explore.partials.ticket-widget',
    ];

    public function index(Request $request, RewardService $rewards, NotificationPresenter $presenter, OrgAccess $access)
    {
        $user = $request->user();
        $active = Ticket::with('service.location', 'counter')->where('user_id', $user->id)
            ->whereIn('status', Ticket::ACTIVE)->latest()->get();
        $recentTickets = Ticket::with('service.location')->where('user_id', $user->id)
            ->whereNotIn('status', Ticket::ACTIVE)->latest()->take(4)->get();
        $notifications = $user->notifications()->latest()->take(5)->get()->map(fn ($n) => $presenter->present($n));

        $shortcuts = collect([
            ['Find a service', 'Search offices and join a queue', 'bi-search', 'find', ''],
            ['Notifications', 'Queue alerts and updates', 'bi-bell', 'platform.notifications', 'violet'],
            ['Rewards', 'Redeem WaitPoints', 'bi-stars', 'platform.rewards', 'amber'],
            ['Settings', 'Profile, privacy & data', 'bi-sliders', 'platform.settings', 'green'],
            ['Library', 'Books, comics & articles', 'bi-book', 'library.index', 'violet'],
            ['English practice', 'Speak & get feedback', 'bi-mic', 'english.index', ''],
            ['Games', 'Play while you wait', 'bi-controller', 'games.index', 'rose'],
        ])->filter(fn ($s) => Route::has($s[3]))->values();

        return view('platform.dashboard', [
            'user' => $user,
            'active' => $active,
            'recentTickets' => $recentTickets,
            'notifications' => $notifications,
            'unread' => $user->unreadNotifications()->count(),
            'balance' => $rewards->balance($user),
            'shortcuts' => $shortcuts,
            'partials' => collect(self::MODULE_PARTIALS)->filter(fn ($v) => view()->exists($v))->values(),
            'isManager' => $access->isManager($user),
        ]);
    }
}
