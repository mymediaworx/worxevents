<?php

declare(strict_types=1);

namespace Tests\Unit\Http;

use App\Http\Middleware\ForceHttps;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;
use Tests\TestCase;

final class ForceHttpsTest extends TestCase
{
    public function test_production_http_redirects_and_https_does_not(): void
    {
        $this->app['env'] = 'production';
        $middleware = new ForceHttps;

        $redirect = $middleware->handle(
            Request::create('http://app.worxevents.com/about', 'GET'),
            fn (): Response => response('ok', 200),
        );
        $this->assertSame(301, $redirect->getStatusCode());
        $this->assertSame('https://app.worxevents.com/about', $redirect->headers->get('Location'));

        $organizer = $middleware->handle(
            Request::create('http://demo.worxevents.com/e/demo-paid-showcase', 'GET'),
            fn (): Response => response('ok', 200),
        );
        $this->assertSame(301, $organizer->getStatusCode());
        $this->assertSame('https://demo.worxevents.com/e/demo-paid-showcase', $organizer->headers->get('Location'));

        $secure = $middleware->handle(
            Request::create('https://app.worxevents.com/about', 'GET'),
            fn (): Response => response('ok', 200),
        );
        $this->assertSame(200, $secure->getStatusCode());
    }

    public function test_non_production_http_is_left_alone(): void
    {
        $response = (new ForceHttps)->handle(
            Request::create('http://app.worxevents.test/about', 'GET'),
            fn (): Response => response('ok', 200),
        );

        $this->assertSame(200, $response->getStatusCode());
    }
}
