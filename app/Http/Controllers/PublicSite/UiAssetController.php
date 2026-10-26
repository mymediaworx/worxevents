<?php

declare(strict_types=1);

namespace App\Http\Controllers\PublicSite;

use App\Http\Controllers\Controller;
use Symfony\Component\HttpFoundation\Response;

/**
 * The SiteGround document root only receives index.php and .htaccess, so a
 * file under public/ is not reachable until this route (or a later copy into
 * public_html) serves it. Same approach as ReserveWorx /ui/app.css.
 */
final class UiAssetController extends Controller
{
    public function stylesheet(): Response
    {
        return $this->file(public_path('css/worxevents.css'), 'text/css; charset=UTF-8');
    }

    public function script(string $file): Response
    {
        if (preg_match('/\A[a-z0-9-]+\.js\z/', $file) !== 1) {
            abort(404);
        }

        return $this->file(public_path('js/'.$file), 'text/javascript; charset=UTF-8', 3600);
    }

    public function demoImage(string $file): Response
    {
        if (preg_match('/\A[a-z0-9-]+\.svg\z/', $file) !== 1) {
            abort(404);
        }

        return $this->file(public_path('images/demo/'.$file), 'image/svg+xml');
    }

    private function file(string $path, string $type, int $maxAge = 86400): Response
    {
        abort_unless(is_file($path), 404);

        return response((string) file_get_contents($path), 200, [
            'Content-Type' => $type,
            'Cache-Control' => 'public, max-age='.$maxAge,
            'X-Content-Type-Options' => 'nosniff',
        ]);
    }
}
