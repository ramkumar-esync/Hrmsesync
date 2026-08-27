<?php

declare(strict_types=1);

namespace HR\Payroll\Infrastructure\Pdf;

use HR\Shared\Domain\ValueObject\Money;

/**
 * Spells out the net pay under the figure — a long-standing payslip convention
 * that makes an altered digit obvious. Written by hand rather than via intl so
 * the PDF renders identically on any server.
 */
final class AmountInWords
{
    private const UNITS = [
        'zero', 'one', 'two', 'three', 'four', 'five', 'six', 'seven', 'eight', 'nine',
        'ten', 'eleven', 'twelve', 'thirteen', 'fourteen', 'fifteen', 'sixteen',
        'seventeen', 'eighteen', 'nineteen',
    ];

    private const TENS = [
        2 => 'twenty', 3 => 'thirty', 4 => 'forty', 5 => 'fifty',
        6 => 'sixty', 7 => 'seventy', 8 => 'eighty', 9 => 'ninety',
    ];

    private const SCALES = [
        1_000_000_000 => 'billion',
        1_000_000 => 'million',
        1_000 => 'thousand',
    ];

    public static function for(Money $money, string $majorUnit = 'ringgit', string $minorUnit = 'sen'): string
    {
        $minorUnits = abs($money->minorUnits);
        $major = intdiv($minorUnits, 100);
        $minor = $minorUnits % 100;

        $words = self::spell($major).' '.$majorUnit;

        if ($minor > 0) {
            $words .= ' and '.self::spell($minor).' '.$minorUnit;
        }

        return ucfirst($words).' only';
    }

    private static function spell(int $number): string
    {
        if ($number < 20) {
            return self::UNITS[$number];
        }

        if ($number < 100) {
            $remainder = $number % 10;

            return self::TENS[intdiv($number, 10)].($remainder > 0 ? '-'.self::UNITS[$remainder] : '');
        }

        if ($number < 1000) {
            $remainder = $number % 100;

            return self::UNITS[intdiv($number, 100)].' hundred'
                .($remainder > 0 ? ' and '.self::spell($remainder) : '');
        }

        foreach (self::SCALES as $value => $name) {
            if ($number >= $value) {
                $remainder = $number % $value;

                return self::spell(intdiv($number, $value)).' '.$name
                    .($remainder > 0 ? ' '.self::spell($remainder) : '');
            }
        }

        return (string) $number;
    }
}
