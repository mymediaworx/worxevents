<?php

declare(strict_types=1);

namespace Tests\Feature\Readiness;

use Tests\TestCase;

final class UiAssetTest extends TestCase
{
    public function test_stylesheet_script_and_demo_art_are_served_without_a_build(): void
    {
        $css = $this->get($this->hostUrl('app.worxevents.test', '/css/worxevents.css'));
        $css->assertOk();
        $css->assertHeader('content-type', 'text/css; charset=UTF-8');
        $body = $css->getContent();
        $this->assertIsString($body);
        $this->assertStringContainsString('--brand: #0F5C4D', $body);
        $this->assertStringContainsString('--accent: #F59E0B', $body);
        $this->assertStringContainsString('prefers-reduced-motion', $body);
        $this->assertStringContainsString('focus-visible', $body);

        $script = $this->get($this->hostUrl('app.worxevents.test', '/js/worxevents.js'));
        $script->assertOk();
        $script->assertHeader('content-type', 'text/javascript; charset=UTF-8');

        $scan = $this->get($this->hostUrl('app.worxevents.test', '/js/check-in.js'));
        $scan->assertOk();
        $scan->assertHeader('content-type', 'text/javascript; charset=UTF-8');

        $image = $this->get($this->hostUrl('app.worxevents.test', '/images/demo/demo-community-night.svg'));
        $image->assertOk();
        $image->assertHeader('content-type', 'image/svg+xml');

        $this->get($this->hostUrl('app.worxevents.test', '/images/demo/not-a-photo.png'))
            ->assertNotFound();
        $this->get($this->hostUrl('app.worxevents.test', '/js/../.env'))
            ->assertNotFound();
    }
}
