<?php

declare(strict_types=1);

namespace Tests\Unit\Support;

use App\Support\MailReport;
use RuntimeException;
use Tests\TestCase;

final class MailReportTest extends TestCase
{
    public function test_failure_and_reply_lines_omit_credentials(): void
    {
        config(['mail.mailers.smtp.password' => 's3cret-quote']);

        $line = MailReport::failure(new RuntimeException(
            "auth failed for s3cret-quote at smtps://noreply:s3cret-quote@mail.worxevents.com:465\nnext",
        ));

        $this->assertStringNotContainsString('s3cret-quote', $line);
        $this->assertStringContainsString('mail_error=RuntimeException', $line);
        $this->assertStringContainsString('://***@', $line);

        $reply = MailReport::smtpReply(implode("\n", [
            '[2026-10-26T00:00:00.000000+00:00] > AUTH PLAIN s3cret-quote',
            '[2026-10-26T00:00:00.000000+00:00] < 250-AUTH PLAIN LOGIN',
            '[2026-10-26T00:00:00.000000+00:00] < 250 2.0.0 Ok: queued as 4ABC123',
        ]));

        $this->assertSame('250 2.0.0 Ok: queued as 4ABC123', $reply);
        $this->assertStringNotContainsString('s3cret-quote', $reply);
    }
}
