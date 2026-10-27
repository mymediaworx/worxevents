<?php

declare(strict_types=1);

namespace Tests\Feature\Mail;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Mail\Transport\ArrayTransport;
use Illuminate\Support\Facades\Mail;
use Tests\TestCase;

final class MailProbeTest extends TestCase
{
    use RefreshDatabase;

    public function test_mail_test_sends_without_printing_a_secret(): void
    {
        config(['mail.mailers.smtp.password' => 's3cret-quote']);

        $this->artisan('worxevents:mail-test', ['email' => 'not-an-email'])
            ->expectsOutputToContain('mail_test=failed')
            ->assertFailed();

        $this->artisan('worxevents:mail-test', ['email' => 'michael@brandworx.ai'])
            ->expectsOutputToContain('mail_test=sent')
            ->doesntExpectOutputToContain('s3cret-quote')
            ->assertSuccessful();

        $transport = Mail::mailer()->getSymfonyTransport();
        $this->assertInstanceOf(ArrayTransport::class, $transport);
        $this->assertStringContainsString('WorxEvents mail test', $transport->messages()->last()->toString());
        $this->assertStringContainsString('michael@brandworx.ai', $transport->messages()->last()->toString());
    }

    public function test_password_reset_emails_a_link_and_does_not_print_it(): void
    {
        $user = User::factory()->create(['email' => 'michael@brandworx.ai']);

        $this->artisan('worxevents:password-reset', ['email' => 'missing@brandworx.ai'])
            ->expectsOutputToContain('reset_user=missing')
            ->assertFailed();

        $this->artisan('worxevents:password-reset', ['email' => 'michael@brandworx.ai'])
            ->expectsOutputToContain('reset_link_sent=yes')
            ->expectsOutputToContain('platform_host=app.worxevents.test')
            ->doesntExpectOutputToContain('reset-password/')
            ->assertSuccessful();

        $this->assertDatabaseCount('password_reset_tokens', 1);
        $transport = Mail::mailer()->getSymfonyTransport();
        $this->assertInstanceOf(ArrayTransport::class, $transport);
        $body = $transport->messages()->last()->toString();
        $this->assertStringContainsString('reset-password/', $body);
        $this->assertStringContainsString('michael@brandworx.ai', $body);
        $this->assertNotSame('', $user->fresh()->email);
    }
}
