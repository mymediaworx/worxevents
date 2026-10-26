<?php

declare(strict_types=1);

namespace App\Domain\Growth;

use Illuminate\Http\Request;

/**
 * The analytics script is emitted only after the visitor opts in, and only
 * when an https hook is configured. The default configuration loads nothing.
 */
final class AnalyticsHook
{
    public function src(Request $request): ?string
    {
        if ($request->cookie('worxevents_analytics') !== '1') {
            return null;
        }

        $hook = config('worxevents.analytics_hook');

        if (! is_string($hook) || preg_match('/\Ahttps:\/\/[A-Za-z0-9._~:\/?#&=-]{1,200}\z/', $hook) !== 1) {
            return null;
        }

        return $hook;
    }
}
