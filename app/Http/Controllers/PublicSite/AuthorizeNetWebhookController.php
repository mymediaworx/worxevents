<?php

declare(strict_types=1);

namespace App\Http\Controllers\PublicSite;

use App\Domain\Payments\AuthorizeNetNotifications;
use App\Http\Controllers\Controller;
use App\Models\Organization;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Str;

final class AuthorizeNetWebhookController extends Controller
{
    public function __construct(private readonly AuthorizeNetNotifications $notifications) {}

    public function store(Request $request, string $organizationId): Response
    {
        if (! Str::isUlid($organizationId)) {
            abort(404);
        }

        $organization = Organization::query()->whereKey($organizationId)->first();

        if (! $organization instanceof Organization) {
            abort(404);
        }

        $host = $request->attributes->get('organization');

        if ($host instanceof Organization && $host->id !== $organization->id) {
            abort(403);
        }

        $status = $this->notifications->handle(
            $organization,
            $request->getContent(),
            (string) $request->header('X-ANET-Signature', ''),
        );

        return response($status === 200 ? 'ok' : '', $status);
    }
}
