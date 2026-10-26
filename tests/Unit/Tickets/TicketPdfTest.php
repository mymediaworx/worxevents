<?php

declare(strict_types=1);

namespace Tests\Unit\Tickets;

use App\Domain\Tickets\TicketPdf;
use PHPUnit\Framework\TestCase;

final class TicketPdfTest extends TestCase
{
    public function test_pdf_contains_the_token_and_draws_dark_modules(): void
    {
        $pdf = TicketPdf::render('Jazz Night', 'Ada Lovelace', 'Monday', 'ABCD1234', str_repeat('ab', 32), [
            [true, false],
            [false, true],
        ]);

        $this->assertStringStartsWith('%PDF-1.4', $pdf);
        $this->assertStringContainsString(str_repeat('ab', 32), $pdf);
        $this->assertStringContainsString(' re f', $pdf);
    }
}
