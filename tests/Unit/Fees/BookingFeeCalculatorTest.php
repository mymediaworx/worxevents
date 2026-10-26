<?php

declare(strict_types=1);

namespace Tests\Unit\Fees;

use App\Domain\Fees\BookingFeeCalculator;
use App\Domain\Fees\FeeBasis;
use App\Domain\Fees\FeeRuleData;
use App\Domain\Fees\MoneyFormat;
use App\Domain\Fees\PercentOfCents;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

final class BookingFeeCalculatorTest extends TestCase
{
    private BookingFeeCalculator $calculator;

    private FeeRuleData $rule;

    protected function setUp(): void
    {
        parent::setUp();

        $this->calculator = new BookingFeeCalculator;
        $this->rule = new FeeRuleData(300, 100, 500);
    }

    #[DataProvider('commercialCases')]
    public function test_commercial_schedule(int $ticketCents, int $expectedFee): void
    {
        $quote = $this->calculator->quote($this->rule, new FeeBasis($ticketCents));

        $this->assertSame($expectedFee, $quote->feeCents);
    }

    /**
     * @return array<string, array{0: int, 1: int}>
     */
    public static function commercialCases(): array
    {
        return [
            '$10 floors at $1' => [1000, 100],
            '$20 floors at $1' => [2000, 100],
            '$33.33 floors at $1' => [3333, 100],
            '$50 is $1.50' => [5000, 150],
            '$100 is $3' => [10000, 300],
            '$166.67 caps at $5' => [16667, 500],
            '$200 caps at $5' => [20000, 500],
            '$500 caps at $5' => [50000, 500],
            'free is $0' => [0, 0],
        ];
    }

    public function test_half_up_rounds_half_a_cent_away_from_zero(): void
    {
        $this->assertSame(1, PercentOfCents::apply(1, 5000));
        $this->assertSame(0, PercentOfCents::apply(1, 4999));
    }

    public function test_several_tickets_produce_one_fee(): void
    {
        $quote = $this->calculator->quote(
            $this->rule,
            FeeBasis::fromTickets([2500, 2500]),
        );

        $this->assertSame(5000, $quote->eligibleCents);
        $this->assertSame(150, $quote->feeCents);
    }

    public function test_discount_is_applied_before_the_percent_and_non_tickets_are_excluded(): void
    {
        $quote = $this->calculator->quote(
            $this->rule,
            FeeBasis::fromTickets(
                ticketLineCents: [10000],
                discountCents: 5000,
                merchandiseCents: 20000,
                donationCents: 1000,
                addOnCents: 500,
                taxCents: 800,
            ),
        );

        $this->assertSame(5000, $quote->eligibleCents);
        $this->assertSame(150, $quote->feeCents);
    }

    public function test_a_discount_that_zeros_the_tickets_is_free_even_with_a_minimum(): void
    {
        $quote = $this->calculator->quote($this->rule, new FeeBasis(1000, 1000));

        $this->assertSame(0, $quote->feeCents);
    }

    public function test_a_custom_rule_can_change_percent_minimum_and_maximum(): void
    {
        $rule = new FeeRuleData(1000, 0, 100000);
        $quote = $this->calculator->quote($rule, new FeeBasis(10000));

        $this->assertSame(1000, $quote->feeCents);
    }

    public function test_money_strings_stay_in_integer_cents(): void
    {
        $this->assertSame(150, MoneyFormat::dollarsToCents('1.50'));
        $this->assertSame(100, MoneyFormat::dollarsToCents('1'));
        $this->assertSame('1.50', MoneyFormat::centsToDollars(150));
        $this->assertSame(300, MoneyFormat::percentToBps('3'));
        $this->assertSame(350, MoneyFormat::percentToBps('3.50'));
        $this->assertSame('3.50', MoneyFormat::bpsToPercent(350));
    }
}
