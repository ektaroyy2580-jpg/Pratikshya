<?php

namespace App\Http\Controllers\Platform;

use App\Http\Controllers\Controller;
use App\Services\Platform\NotificationPresenter;
use Illuminate\Http\Request;

/** Notifications centre. Every lookup is scoped to $request->user()->notifications() (no IDOR). */
class NotificationsController extends Controller
{
    public function index(Request $request, NotificationPresenter $presenter)
    {
        $user = $request->user();
        $filter = $request->query('filter') === 'unread' ? 'unread' : 'all';
        $page = ($filter === 'unread' ? $user->unreadNotifications() : $user->notifications())->latest()->paginate(20)->withQueryString();
        $items = $page->getCollection()->map(fn ($n) => $presenter->present($n));

        return view('platform.notifications', [
            'page' => $page,
            'items' => $items,
            'filter' => $filter,
            'unread' => $user->unreadNotifications()->count(),
            'total' => $user->notifications()->count(),
        ]);
    }

    public function unreadCount(Request $request)
    {
        $user = $request->user();
        $latest = $user->unreadNotifications()->latest()->first();

        return response()->json([
            'message' => 'ok',
            'data' => [
                'unread' => $user->unreadNotifications()->count(),
                'latest' => $latest ? ['id' => $latest->id, 'message' => (string) ($latest->data['message'] ?? '')] : null,
            ],
        ])->header('Cache-Control', 'no-store');
    }

    public function read(Request $request, string $id, NotificationPresenter $presenter)
    {
        $n = $request->user()->notifications()->whereKey($id)->firstOrFail();
        $n->markAsRead();

        if ($request->expectsJson()) {
            return response()->json(['message' => 'Marked as read.', 'data' => ['id' => $n->id, 'unread' => $request->user()->unreadNotifications()->count()]]);
        }
        if ($request->boolean('open') && ($url = $presenter->present($n)['url'])) {
            return redirect($url);
        }

        return back()->with('ok', 'Marked as read.');
    }

    public function readAll(Request $request)
    {
        $count = $request->user()->unreadNotifications()->update(['read_at' => now()]);

        if ($request->expectsJson()) {
            return response()->json(['message' => 'All caught up.', 'data' => ['marked' => $count, 'unread' => 0]]);
        }

        return back()->with('ok', $count ? "Marked {$count} as read." : 'You are all caught up.');
    }
}
