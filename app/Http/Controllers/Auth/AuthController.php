<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Services\GuestMerger;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Password;

class AuthController extends Controller
{
    public function showRegister()
    {
        return view('auth.register');
    }

    public function register(Request $request)
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'email', 'max:255', Rule::unique('users', 'email')],
            'password' => ['required', 'confirmed', Password::min(8)],
        ]);

        $current = $request->user();
        if ($current?->is_guest) {
            // Keep everything the guest has learned: upgrade the same account.
            $current->fill($data);
            $current->is_guest = false;
            $current->save();
            $user = $current;
        } else {
            $user = User::create($data);
        }

        Auth::login($user, remember: true);
        $request->session()->regenerate();

        return redirect()->route('course')->with('status', 'Tukusanyukidde! Your progress is saved to your account.');
    }

    public function showLogin()
    {
        return view('auth.login');
    }

    public function login(Request $request, GuestMerger $merger)
    {
        $credentials = $request->validate([
            'email' => ['required', 'email'],
            'password' => ['required'],
        ]);
        $guest = $request->user()?->is_guest ? $request->user() : null;

        // At most 5 wrong passwords a minute for one email from one network: stops password guessing.
        $key = 'login:'.mb_strtolower($credentials['email']).'|'.$request->ip();
        if (RateLimiter::tooManyAttempts($key, 5)) {
            return back()->withErrors(['email' => 'Too many tries. Wait '.RateLimiter::availableIn($key).' seconds, then try again.'])->onlyInput('email');
        }
        if (! Auth::attempt($credentials, $request->boolean('remember'))) {
            RateLimiter::hit($key, 60);

            return back()->withErrors(['email' => 'Those details do not match our records.'])->onlyInput('email');
        }
        RateLimiter::clear($key);
        if ($guest) {
            $merger->merge($guest, Auth::user());
        }
        $request->session()->regenerate();

        return redirect()->intended(route('course'));
    }

    public function logout(Request $request)
    {
        Auth::logout();
        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return redirect()->route('home');
    }
}
