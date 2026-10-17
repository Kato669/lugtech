<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/** Like Laravel's "guest" middleware, but guest learners may still sign up or log in. */
class RedirectIfRegistered
{
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();
        if ($user && ! $user->is_guest) {
            return redirect()->route('course');
        }

        return $next($request);
    }
}
