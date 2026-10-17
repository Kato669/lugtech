<?php

namespace App\Http\Middleware;

use App\Models\User;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Str;
use Symfony\Component\HttpFoundation\Response;

/**
 * Lets visitors learn without signing up: anyone not logged in gets a guest
 * account (remembered by cookie) so lessons, review and games all work.
 * Registering later turns the guest account into a full one, keeping progress.
 */
class EnsureLearner
{
    public function handle(Request $request, Closure $next): Response
    {
        if (! Auth::check()) {
            // Don't create accounts for crawlers.
            if (preg_match('/bot|crawl|spider|slurp|preview/i', (string) $request->userAgent())) {
                return redirect()->route('home');
            }
            // A script that ignores cookies would make a new account on every visit. Allow plenty
            // (a whole classroom can share one internet connection), but not thousands.
            $key = 'new-guest:'.$request->ip();
            if (RateLimiter::tooManyAttempts($key, (int) config('luganda.guests_per_hour', 100))) {
                abort(429, 'Too many new visitors from this network. Please try again later, or log in.');
            }
            RateLimiter::hit($key, 3600);
            $guest = User::create([
                'name' => 'Guest',
                'email' => 'guest-'.Str::uuid().'@guest.invalid',
                'password' => Str::random(40),
            ]);
            $guest->forceFill(['is_guest' => true])->save();
            Auth::login($guest, remember: true);
            $request->session()->regenerate();
        }

        return $next($request);
    }
}
