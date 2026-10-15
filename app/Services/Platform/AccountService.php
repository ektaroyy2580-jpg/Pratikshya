<?php

namespace App\Services\Platform;

use App\Models\AuditLog;
use App\Models\RewardRedemption;
use App\Models\RewardTransaction;
use App\Models\StaffAssignment;
use App\Models\Ticket;
use App\Models\User;
use App\Models\UserPreference;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/**
 * Personal-data operations for the signed-in user only.
 *
 * Retention policy (documented on /settings):
 *  - Export: every record below is selected by the user's own id — never by request input.
 *  - Deletion request: personal fields are anonymised immediately (name, email, phone,
 *    password, notifications, preferences, org memberships). Queue records (tickets,
 *    queue events) and the points ledger are KEPT, de-identified, because staff
 *    audit trails, wait-time statistics and fraud checks depend on them.
 */
class AccountService
{
    public function export(User $user): array
    {
        $prefs = UserPreference::where('user_id', $user->id)->first();

        return [
            'exported_at' => now()->toIso8601String(),
            'notice' => 'Pratikshya local demo — your personal data export. Contains only records linked to your account.',
            'account' => [
                'name' => $user->name,
                'email' => $user->email,
                'phone' => $user->phone,
                'role' => $user->role,
                'created_at' => $user->created_at?->toIso8601String(),
            ],
            'preferences' => $prefs ? [
                'notification_channels' => $prefs->channels(),
                'marketing_opt_in' => $prefs->marketing_opt_in,
                'personalised_ads' => $prefs->personalised_ads,
                'location_suggestions' => $prefs->location_suggestions,
            ] : null,
            'tickets' => Ticket::with('service.location')->where('user_id', $user->id)->orderBy('id')->get()
                ->map(fn (Ticket $t) => [
                    'token' => $t->service ? $t->token() : null,
                    'service' => $t->service?->name,
                    'location' => $t->service?->location?->name,
                    'status' => $t->status,
                    'docs_verified' => (bool) $t->docs_verified,
                    'joined_at' => $t->created_at?->toIso8601String(),
                    'called_at' => $t->called_at?->toIso8601String(),
                    'completed_at' => $t->completed_at?->toIso8601String(),
                ])->all(),
            'notifications' => $user->notifications()->orderBy('created_at')->get()
                ->map(fn ($n) => ['data' => $n->data, 'read_at' => $n->read_at?->toIso8601String(), 'created_at' => $n->created_at?->toIso8601String()])->all(),
            'reward_transactions' => RewardTransaction::where('user_id', $user->id)->orderBy('id')->get()
                ->map(fn ($r) => ['points' => $r->points, 'source' => $r->source, 'reason' => $r->reason, 'created_at' => $r->created_at?->toIso8601String()])->all(),
            'reward_redemptions' => RewardRedemption::with('item')->where('user_id', $user->id)->orderBy('id')->get()
                ->map(fn ($r) => ['reward' => $r->item?->title, 'points' => $r->points, 'code' => $r->code, 'status' => $r->status, 'expires_at' => $r->expires_at?->toIso8601String()])->all(),
            'staff_assignments' => StaffAssignment::with('location')->where('user_id', $user->id)->get()
                ->map(fn ($a) => ['location' => $a->location?->name, 'approved' => $a->is_approved])->all(),
            'organization_memberships' => DB::table('organization_user')->join('organizations', 'organizations.id', '=', 'organization_user.organization_id')
                ->where('organization_user.user_id', $user->id)->get(['organizations.name', 'organization_user.role'])
                ->map(fn ($m) => ['organization' => $m->name, 'role' => $m->role])->all(),
        ];
    }

    /** Irreversibly de-identify the account. Caller must have verified password + confirmation. */
    public function anonymise(User $user): void
    {
        DB::transaction(function () use ($user) {
            AuditLog::record('account_anonymised', $user, []);
            $user->notifications()->delete();
            DB::table('organization_user')->where('user_id', $user->id)->delete();
            StaffAssignment::where('user_id', $user->id)->delete();
            Ticket::where('user_id', $user->id)->update(['name' => 'Deleted user']);
            $prefs = UserPreference::for($user);
            $prefs->forceFill([
                'notification_channels' => UserPreference::DEFAULT_CHANNELS,
                'marketing_opt_in' => false, 'personalised_ads' => false, 'location_suggestions' => false,
                'anonymised_at' => now(),
            ])->save();
            $user->forceFill([
                'name' => 'Deleted user',
                'email' => 'deleted-'.$user->id.'-'.Str::lower(Str::random(10)).'@deleted.invalid',
                'phone' => null,
                'role' => 'user',
                'password' => Str::random(64),
                'remember_token' => null,
            ])->save();
        });
    }
}
