<?php

declare(strict_types=1);

namespace App\Http\Controllers\PublicSite;

use App\Http\Controllers\Controller;
use App\Models\Event;
use App\Models\Organization;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\StreamedResponse;

final class MediaController extends Controller
{
    public function logo(Request $request): StreamedResponse
    {
        $organization = $request->attributes->get('organization');

        if (! $organization instanceof Organization) {
            abort(404);
        }

        return $this->stream($organization->logo_path, 'organizations/'.$organization->id.'/');
    }

    public function eventImage(Request $request, Event $event): StreamedResponse
    {
        if (! $event->isPubliclyVisible()) {
            $user = $request->user();

            if (! $user instanceof User || ! Gate::forUser($user)->allows('events.manage')) {
                abort(404);
            }
        }

        return $this->stream($event->image_path, 'events/'.$event->organization_id.'/');
    }

    private function stream(?string $path, string $prefix): StreamedResponse
    {
        if ($path === null || str_contains($path, '..') || ! str_starts_with($path, $prefix)) {
            abort(404);
        }

        $extension = strtolower(pathinfo($path, PATHINFO_EXTENSION));
        $mime = match ($extension) {
            'jpg', 'jpeg' => 'image/jpeg',
            'png' => 'image/png',
            'webp' => 'image/webp',
            'gif' => 'image/gif',
            default => 'application/octet-stream',
        };

        return Storage::disk('local')->response($path, null, [
            'Content-Type' => $mime,
            'X-Content-Type-Options' => 'nosniff',
            'Content-Disposition' => 'inline',
        ]);
    }
}
