<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Stores referral and UTM parameters from the query string in the session.
 * Checkout copies them onto the order. They are not trusted as instructions.
 */
final class RememberCampaign
{
    /**
     * @param  Closure(Request): Response  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        $map = [
            'ref' => 'ref',
            'utm_source' => 'utm_source',
            'utm_medium' => 'utm_medium',
            'utm_campaign' => 'utm_campaign',
        ];

        foreach ($map as $query => $key) {
            $value = $request->query($query);

            if (! is_string($value) || preg_match('/\A[A-Za-z0-9._~-]{1,80}\z/', $value) !== 1) {
                continue;
            }

            if ($query === 'ref' && strlen($value) > 40) {
                continue;
            }

            $request->session()->put('campaign.'.$key, $value);
        }

        return $next($request);
    }
}
