<?php

declare(strict_types=1);

namespace Tests\Unit;

use HR\Payroll\Domain\ValueObject\PayPeriod;
use HR\Shared\Domain\Exception\InvariantViolation;
use PHPUnit\Framework\TestCase;

final class PayPeriodTest extends TestCase
{
    public function test_it_covers_the_whole_calendar_month(): void
    {
        $period = PayPeriod::fromString('2026-02');

        $this->assertSame('2026-02-01', $period->startDate()->toDateString());
        $this->assertSame('2026-02-28', $period->endDate()->toDateString());
        $this->assertSame('February 2026', $period->label());
    }

    public function test_it_handles_leap_years(): void
    {
        $this->assertSame('2028-02-29', PayPeriod::fromString('2028-02')->endDate()->toDateString());
    }

    public function test_it_rolls_over_the_year_boundary(): void
    {
        $this->assertSame('2027-01', (string) PayPeriod::fromString('2026-12')->next());
        $this->assertSame('2025-12', (string) PayPeriod::fromString('2026-01')->previous());
    }

    public function test_it_rejects_a_malformed_period(): void
    {
        $this->expectException(InvariantViolation::class);

        PayPeriod::fromString('July 2026');
    }
}
