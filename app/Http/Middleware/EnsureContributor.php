<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/** Recording studio and content review: admins and native-speaker reviewers. */
class EnsureContributor
{
    public function handle(Request $request, Closure $next): Response
    {
        abort_unless($request->user()?->isContributor(), 403);

        return $next($request);
    }
}
