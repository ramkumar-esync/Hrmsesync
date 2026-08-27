<?php

declare(strict_types=1);

namespace HR\Shared\Domain\ValueObject;

use HR\Shared\Domain\Exception\InvariantViolation;

/**
 * Money held as integer minor units (sen/cents).
 *
 * Floats are never used for amounts anywhere in the payroll domain. Every
 * rounding decision is explicit and happens here, so a payslip that adds up on
 * screen also adds up in the ledger.
 */
final readonly class Money implements \JsonSerializable, \Stringable
{
    private function __construct(
        public int $minorUnits,
        public string $currency,
    ) {}

    public static function of(int $minorUnits, ?string $currency = null): self
    {
        return new self($minorUnits, self::normaliseCurrency($currency));
    }

    public static function zero(?string $currency = null): self
    {
        return new self(0, self::normaliseCurrency($currency));
    }

    /** Build from a decimal string such as "3500.00". Strings avoid float drift. */
    public static function fromDecimal(string|int|float $amount, ?string $currency = null): self
    {
        $amount = is_string($amount) ? trim($amount) : (string) $amount;

        if (! preg_match('/^-?\d+(\.\d{1,2})?$/', $amount)) {
            throw InvariantViolation::because("Amount \"{$amount}\" is not a valid monetary value.");
        }

        $negative = str_starts_with($amount, '-');
        $amount = ltrim($amount, '-');
        [$whole, $fraction] = array_pad(explode('.', $amount, 2), 2, '0');
        $minor = (int) $whole * 100 + (int) str_pad($fraction, 2, '0');

        return new self($negative ? -$minor : $minor, self::normaliseCurrency($currency));
    }

    public function add(self $other): self
    {
        $this->assertSameCurrency($other);

        return new self($this->minorUnits + $other->minorUnits, $this->currency);
    }

    public function subtract(self $other): self
    {
        $this->assertSameCurrency($other);

        return new self($this->minorUnits - $other->minorUnits, $this->currency);
    }

    /** Multiply by a rate, rounding half up on the final sen. */
    public function multipliedBy(float $rate): self
    {
        return new self((int) round($this->minorUnits * $rate, 0, PHP_ROUND_HALF_UP), $this->currency);
    }

    public function percentage(float $percent): self
    {
        return $this->multipliedBy($percent / 100);
    }

    /** Round up to the next whole ringgit/dollar — how EPF wage bands behave. */
    public function roundedUpToUnit(): self
    {
        $remainder = $this->minorUnits % 100;

        return $remainder === 0
            ? $this
            : new self($this->minorUnits + (100 - $remainder), $this->currency);
    }

    public function cappedAt(self $ceiling): self
    {
        $this->assertSameCurrency($ceiling);

        return $this->minorUnits > $ceiling->minorUnits ? $ceiling : $this;
    }

    public function isZero(): bool
    {
        return $this->minorUnits === 0;
    }

    public function isNegative(): bool
    {
        return $this->minorUnits < 0;
    }

    public function isGreaterThan(self $other): bool
    {
        $this->assertSameCurrency($other);

        return $this->minorUnits > $other->minorUnits;
    }

    public function equals(self $other): bool
    {
        return $this->minorUnits === $other->minorUnits && $this->currency === $other->currency;
    }

    /** @param iterable<self> $amounts */
    public static function sum(iterable $amounts, ?string $currency = null): self
    {
        $total = self::zero($currency);

        foreach ($amounts as $amount) {
            $total = $total->add($amount);
        }

        return $total;
    }

    public function toDecimal(): string
    {
        $sign = $this->minorUnits < 0 ? '-' : '';
        $abs = abs($this->minorUnits);

        return sprintf('%s%d.%02d', $sign, intdiv($abs, 100), $abs % 100);
    }

    public function format(): string
    {
        return $this->currency.' '.number_format($this->minorUnits / 100, 2);
    }

    /** @return array{amount: string, currency: string, minor_units: int} */
    public function jsonSerialize(): array
    {
        return [
            'amount' => $this->toDecimal(),
            'currency' => $this->currency,
            'minor_units' => $this->minorUnits,
        ];
    }

    public function __toString(): string
    {
        return $this->format();
    }

    private function assertSameCurrency(self $other): void
    {
        if ($this->currency !== $other->currency) {
            throw InvariantViolation::because(
                "Cannot combine {$this->currency} with {$other->currency}."
            );
        }
    }

    private static function normaliseCurrency(?string $currency): string
    {
        $currency = strtoupper($currency ?? self::defaultCurrency());

        if (! preg_match('/^[A-Z]{3}$/', $currency)) {
            throw InvariantViolation::because("\"{$currency}\" is not a 3-letter currency code.");
        }

        return $currency;
    }

    /**
     * Reads the configured currency when running inside the application, and
     * falls back to a constant otherwise — so the domain layer can be unit
     * tested without booting the framework.
     */
    private static function defaultCurrency(): string
    {
        if (function_exists('config')) {
            $configured = config('payroll.currency');

            if (is_string($configured) && $configured !== '') {
                return $configured;
            }
        }

        return 'MYR';
    }
}
