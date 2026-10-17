<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Symfony\Component\HttpFoundation\Response;

/**
 * Saves sent again (a phone that was offline sends its queue, or retries after a dropped
 * signal) carry the same X-Request-Id. The first answer is remembered for a day and given
 * again, so a finished lesson, a review or a game is never counted twice.
 */
class OnceOnly
{
    public const TTL_SECONDS = 86400;

    public function handle(Request $request, Closure $next): Response
    {
        $id = (string) $request->header('X-Request-Id');
        if (! $request->user() || ! preg_match('/^[0-9a-f-]{36}$/i', $id)) {
            return $next($request);
        }
        $key = 'once:'.$request->user()->id.':'.strtolower($id);

        // One at a time per request id, so two copies arriving together still count once.
        return Cache::lock($key.':lock', 10)->block(5, function () use ($key, $request, $next) {
            if ($seen = Cache::get($key)) {
                return response($seen['body'], $seen['status'], ['Content-Type' => 'application/json', 'X-Repeated-Request' => '1']);
            }
            $response = $next($request);
            if ($response->isSuccessful()) {
                Cache::put($key, ['status' => $response->getStatusCode(), 'body' => $response->getContent()], self::TTL_SECONDS);
            }

            return $response;
        });
    }
}
