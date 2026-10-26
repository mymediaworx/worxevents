<?php

declare(strict_types=1);

namespace App\Http\Controllers\PublicSite;

use App\Domain\Catalog\PublishedEvents;
use App\Http\Controllers\Controller;
use App\Tenancy\OrganizationManager;
use Illuminate\Http\Response;

final class SitemapController extends Controller
{
    public function __construct(
        private readonly PublishedEvents $published,
        private readonly OrganizationManager $organizations,
    ) {}

    public function __invoke(): Response
    {
        $query = $this->published->query()->orderBy('starts_at');
        $organizationId = $this->organizations->id();

        if ($organizationId !== null) {
            $query->where('organization_id', $organizationId);
        }

        $urls = [
            url('/'),
            url('/browse'),
            url('/about'),
            url('/pricing'),
            url('/help'),
            url('/contact'),
            url('/legal/terms'),
            url('/legal/privacy'),
            url('/legal/refunds'),
            url('/accessibility'),
        ];

        foreach ($query->limit(5000)->get() as $event) {
            $urls[] = $this->published->url($event);
        }

        $body = ['<?xml version="1.0" encoding="UTF-8"?>', '<urlset xmlns="http://www.sitemaps.org/schemas/sitemap/0.9">'];

        foreach ($urls as $url) {
            $body[] = '<url><loc>'.htmlspecialchars($url, ENT_XML1).'</loc></url>';
        }

        $body[] = '</urlset>';

        return response(implode('', $body), 200, ['Content-Type' => 'application/xml; charset=UTF-8']);
    }
}
