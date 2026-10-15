<?php

namespace App\Http\Controllers\Platform;

use App\Http\Controllers\Controller;
use App\Models\AuditLog;
use Illuminate\Http\Request;

/** Read-only audit log browser (platform admins). */
class AuditController extends Controller
{
    public function index(Request $request)
    {
        $filters = $request->validate([
            'action' => ['nullable', 'string', 'max:60'],
            'actor' => ['nullable', 'string', 'max:120'],
            'subject' => ['nullable', 'string', 'max:120'],
            'from' => ['nullable', 'date'],
            'to' => ['nullable', 'date'],
        ]);
        $logs = AuditLog::with('actor:id,name,email')
            ->when($filters['action'] ?? null, fn ($q, $v) => $q->where('action', $v))
            ->when($filters['actor'] ?? null, fn ($q, $v) => $q->whereHas('actor', fn ($a) => $a->where('name', 'like', "%{$v}%")->orWhere('email', 'like', "%{$v}%")))
            ->when($filters['subject'] ?? null, fn ($q, $v) => $q->where('subject_type', 'like', "%{$v}%"))
            ->when($filters['from'] ?? null, fn ($q, $v) => $q->where('created_at', '>=', $v))
            ->when($filters['to'] ?? null, fn ($q, $v) => $q->where('created_at', '<=', $v.' 23:59:59'))
            ->latest('id')->paginate(30)->withQueryString();
        $actions = AuditLog::select('action')->distinct()->orderBy('action')->pluck('action');

        return view('platform.manage.audit', compact('logs', 'actions', 'filters'));
    }
}
