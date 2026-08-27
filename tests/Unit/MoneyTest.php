<?php

declare(strict_types=1);

namespace Tests\Unit;

use HR\Shared\Domain\Exception\InvariantViolation;
use HR\Shared\Domain\ValueObject\Money;
use PHPUnit\Framework\TestCase;

/** Pure domain test — no framework boot required. */
final class MoneyTest extends TestCase
{
    public function test_it_parses_decimal_strings_without_float_drift(): void
    {
        $this->assertSame(350000, Money::fromDecimal('3500.00')->minorUnits);
        $this->assertSame(1, Money::fromDecimal('0.01')->minorUnits);
        $this->assertSame(-2550, Money::fromDecimal('-25.50')->minorUnits);

        // The classic float trap: 0.1 + 0.2 must be exactly 0.30 here.
        $sum = Money::fromDecimal('0.10')->add(Money::fromDecimal('0.20'));
        $this->assertSame('0.30', $sum->toDecimal());
    }

    public function test_it_rejects_malformed_amounts(): void
    {
        $this->expectException(InvariantViolation::class);

        Money::fromDecimal('1,500.00');
    }

    public function test_it_rejects_more_than_two_decimal_places(): void
    {
        $this->expectException(InvariantViolation::class);

        Money::fromDecimal('10.005');
    }

    public function test_percentage_rounds_half_up_on_the_final_unit(): void
    {
        // 11% of 3,333.33 = 366.6663 → 366.67
        $this->assertSame(
            '366.67',
            Money::fromDecimal('3333.33')->percentage(11.0)->toDecimal(),
        );
    }

    public function test_it_rounds_up_to_the_next_whole_unit(): void
    {
        $this->assertSame('3501.00', Money::fromDecimal('3500.01')->roundedUpToUnit()->toDecimal());
        $this->assertSame('3500.00', Money::fromDecimal('3500.00')->roundedUpToUnit()->toDecimal());
    }

    public function test_it_caps_at_a_ceiling(): void
    {
        $ceiling = Money::fromDecimal('6000.00');

        $this->assertSame('6000.00', Money::fromDecimal('8200.00')->cappedAt($ceiling)->toDecimal());
        $this->assertSame('4300.00', Money::fromDecimal('4300.00')->cappedAt($ceiling)->toDecimal());
    }

    public function test_it_refuses_to_mix_currencies(): void
    {
        $this->expectException(InvariantViolation::class);

        Money::fromDecimal('10.00', 'MYR')->add(Money::fromDecimal('10.00', 'SGD'));
    }

    public function test_sum_of_many_amounts_stays_exact(): void
    {
        $amounts = array_fill(0, 300, Money::fromDecimal('33.33'));

        $this->assertSame('9999.00', Money::sum($amounts)->toDecimal());
    }
}
