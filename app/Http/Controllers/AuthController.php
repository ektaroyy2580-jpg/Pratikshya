<?php

namespace App\Http\Controllers;

use App\Http\Requests\RegisterRequest;
use App\Models\AuditLog;
use App\Models\Ticket;
use App\Models\User;
use Illuminate\Auth\Events\PasswordReset;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Password;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Str;
use Illuminate\Validation\Rules\Password as PasswordRule;
use Illuminate\Validation\ValidationException;

class AuthController extends Controller
{
    // ---------------- Login ----------------

    public function show()
    {
        return Auth::check() ? redirect()->route('profile') : view('auth.login');
    }

    public function login(Request $request)
    {
        $this->attempt($request);
        $this->claimSessionTickets();

        return redirect()->intended(route('profile'))->with('ok', 'Welcome back, '.Auth::user()->name.'!');
    }

    public function showStaffLogin()
    {
        $u = Auth::user();

        return $u && in_array($u->role, ['staff', 'admin'], true) ? redirect()->route('staff') : view('auth.staff-login');
    }

    /** Staff login authenticates normally, then requires an approved staff role + location. */
    public function staffLogin(Request $request)
    {
        $this->attempt($request);
        $user = Auth::user();
        if (! $user->isAdmin() && ! ($user->isStaff() && $user->staffLocationIds())) {
            AuditLog::record('staff_login_denied', $user);
            Auth::logout();
            $request->session()->invalidate();
            $request->session()->regenerateToken();

            throw ValidationException::withMessages(['email' => 'This account is not an approved staff member with an assigned location.']);
        }
        AuditLog::record('staff_login', $user);

        return redirect()->route($user->isAdmin() ? 'admin' : 'staff');
    }

    private function attempt(Request $request): void
    {
        $data = $request->validate([
            'email' => ['required', 'email', 'max:120'],
            'password' => ['required', 'string', 'max:255'],
        ]);
        $key = 'login:'.Str::lower($data['email']).'|'.$request->ip();
        if (RateLimiter::tooManyAttempts($key, 5)) {
            throw ValidationException::withMessages([
                'email' => 'Too many login attempts. Try again in '.RateLimiter::availableIn($key).' seconds.',
            ]);
        }
        if (! Auth::attempt(['email' => Str::lower($data['email']), 'password' => $data['password']], $request->boolean('remember'))) {
            RateLimiter::hit($key, 60);

            throw ValidationException::withMessages(['email' => 'These credentials do not match our records.']);
        }
        RateLimiter::clear($key);
        $request->session()->regenerate();   // prevent session fixation
    }

    // ---------------- Registration ----------------

    public function showRegister()
    {
        return Auth::check() ? redirect()->route('profile') : view('auth.register');
    }

    public function register(RegisterRequest $request)
    {
        // Only whitelisted fields; `role` can never come from the request.
        $user = User::create($request->safe()->only(['name', 'email', 'phone', 'password']));
        Auth::login($user);
        $request->session()->regenerate();
        $this->claimSessionTickets();

        return redirect()->route('profile')->with('ok', 'Welcome to प्रतीक्षा, '.$user->name.'!');
    }

    public function logout(Request $request)
    {
        Auth::logout();
        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return redirect()->route('home');
    }

    // ---------------- Password reset ----------------

    public function showForgot()
    {
        return view('auth.forgot');
    }

    /** Same response whether or not the email exists (no account enumeration). */
    public function sendReset(Request $request)
    {
        $request->validate(['email' => ['required', 'email', 'max:120']]);
        Password::sendResetLink(['email' => Str::lower($request->input('email'))]);

        return back()->with('status', "If an account exists for that email, we've sent a password reset link.");
    }

    public function showReset(Request $request, string $token)
    {
        return view('auth.reset', ['token' => $token, 'email' => (string) $request->query('email')]);
    }

    public function reset(Request $request)
    {
        $request->validate([
            'token' => ['required', 'string'],
            'email' => ['required', 'email'],
            'password' => ['required', 'confirmed', PasswordRule::min(8)->letters()->numbers()],
        ]);

        $status = Password::reset(
            $request->only('email', 'password', 'password_confirmation', 'token'),
            function (User $user, string $password) {
                $user->forceFill(['password' => $password, 'remember_token' => Str::random(60)])->save();
                event(new PasswordReset($user));
            }
        );

        return $status === Password::PASSWORD_RESET
            ? redirect()->route('login')->with('status', 'Your password has been reset. Please log in.')
            : back()->withInput($request->only('email'))->withErrors(['email' => 'This reset link is invalid or has expired.']);
    }

    // ---------------- Profile ----------------

    public function profile()
    {
        $user = Auth::user();
        $tickets = Ticket::with('service.location', 'counter')->where('user_id', $user->id)->latest()->get();
        $points = $tickets->sum(fn ($t) => $t->game_points + ($t->status === 'completed' ? 50 : 0));
        $reclaimed = $tickets->where('status', 'completed')->sum('initial_wait');
        $notifications = $user->notifications()->latest()->take(30)->get();
        // Read state is managed in the notifications centre; viewing the profile no longer clears it.

        return view('pages.profile', compact('user', 'tickets', 'points', 'reclaimed', 'notifications'));
    }

    /** Tickets joined before logging in (this browser) become the user's. */
    private function claimSessionTickets(): void
    {
        $ids = session('tickets', []);
        if ($ids) {
            Ticket::whereIn('id', $ids)->whereNull('user_id')->update(['user_id' => Auth::id()]);
        }
    }
}
