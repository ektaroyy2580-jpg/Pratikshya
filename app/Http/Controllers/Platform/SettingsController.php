<?php

namespace App\Http\Controllers\Platform;

use App\Http\Controllers\Controller;
use App\Http\Requests\Platform\ChangePasswordRequest;
use App\Http\Requests\Platform\UpdatePreferencesRequest;
use App\Http\Requests\Platform\UpdateProfileRequest;
use App\Models\AuditLog;
use App\Models\UserPreference;
use App\Services\Platform\AccountService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

/** Profile & privacy settings for the signed-in user only (no route parameters → no IDOR surface). */
class SettingsController extends Controller
{
    public static function mailConfigured(): bool
    {
        return ! in_array((string) config('mail.default'), ['log', 'array', ''], true);
    }

    public function show(Request $request)
    {
        $user = $request->user();

        return view('platform.settings', [
            'user' => $user,
            'prefs' => UserPreference::for($user),
            'mailConfigured' => self::mailConfigured(),
        ]);
    }

    public function updateProfile(UpdateProfileRequest $request)
    {
        $user = $request->user();
        $data = $request->validated();
        // Only these two keys are ever written — role/email in the payload are ignored.
        $user->fill(['name' => $data['name'], 'phone' => $data['phone'] ?? null]);
        $changed = array_keys($user->getDirty());
        $user->save();
        if ($changed) {
            AuditLog::record('profile_updated', $user, ['fields' => $changed]);
        }

        return redirect()->route('platform.settings')->with('ok', 'Profile saved.');
    }

    public function updatePassword(ChangePasswordRequest $request)
    {
        $user = $request->user();
        $user->forceFill(['password' => $request->validated()['password']])->setRememberToken(null);
        $user->save();
        $request->session()->regenerate();
        AuditLog::record('password_changed', $user);

        return redirect()->route('platform.settings')->with('ok', 'Password changed.');
    }

    public function updatePreferences(UpdatePreferencesRequest $request)
    {
        $prefs = UserPreference::for($request->user());
        $channels = $prefs->channels();
        $channels['browser_push'] = $request->boolean('browser_push');
        $channels['email'] = self::mailConfigured() && $request->boolean('email');
        $channels['sms'] = false; // not configured
        $channels['database'] = true;
        $prefs->forceFill([
            'notification_channels' => $channels,
            'marketing_opt_in' => $request->boolean('marketing_opt_in'),
            'personalised_ads' => $request->boolean('personalised_ads'),
            'location_suggestions' => $request->boolean('location_suggestions'),
        ])->save();

        if ($request->expectsJson()) {
            return response()->json(['message' => 'Preferences saved.', 'data' => ['channels' => $prefs->channels()]]);
        }

        return redirect()->route('platform.settings')->with('ok', 'Preferences saved.');
    }

    public function export(Request $request, AccountService $accounts)
    {
        $user = $request->user();
        AuditLog::record('data_exported', $user);
        $json = json_encode($accounts->export($user), JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);

        return response($json, 200, [
            'Content-Type' => 'application/json; charset=utf-8',
            'Content-Disposition' => 'attachment; filename="pratikshya-my-data-'.now()->format('Y-m-d').'.json"',
            'Cache-Control' => 'no-store, private',
        ]);
    }

    public function destroy(Request $request, AccountService $accounts)
    {
        $request->validateWithBag('delete', [
            'password' => ['required', 'current_password'],
            'confirm' => ['required', 'in:DELETE'],
        ], ['confirm.in' => 'Type DELETE in capitals to confirm.', 'password.current_password' => 'Your password is incorrect.']);

        $user = $request->user();
        if ($user->isAdmin()) {
            return back()->withErrors(['confirm' => 'Platform admin accounts cannot be deleted here. Ask another admin to change your role first.'], 'delete');
        }

        $accounts->anonymise($user);
        Auth::logout();
        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return redirect()->route('home')->with('ok', 'Your account was deleted and your personal details removed.');
    }
}
