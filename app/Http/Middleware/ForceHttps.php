<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * SiteGround HTTPS Enforce stays off. Production requests that arrive as HTTP
 * redirect to HTTPS. Secure cookies are set separately for production.
 */
final class ForceHttps
{
    /**
     * @param  Closure(Request): Response  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        if (! app()->environment('production') || $request->secure()) {
            return $next($request);
        }

        $host = $request->getHttpHost();
        if (! preg_match('/\A[A-Za-z0-9.-]+(?::[0-9]{1,5})?\z/', $host)) {
            abort(400);
        }

        return redirect()->away('https://'.$host.$request->getRequestUri(), 301);
    }
}
