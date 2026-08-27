<?php

declare(strict_types=1);

namespace HR\Identity\Domain\Service;

/**
 * Generates a temporary password that a person can read off a screen and type
 * once without mistakes.
 *
 * It avoids the characters that get confused in that hand-off — no O/0, l/1/I —
 * and always includes an upper case letter, a lower case letter, a digit and a
 * symbol so it satisfies a typical password policy on the first try. This is a
 * throwaway secret: the account holder is expected to change it, but the system
 * never forces them to.
 */
final class TemporaryPassword
{
    private const UPPER = 'ABCDEFGHJKMNPQRSTUVWXYZ';

    private const LOWER = 'abcdefghjkmnpqrstuvwxyz';

    private const DIGIT = '23456789';

    private const SYMBOL = '@#$%*?';

    public static function generate(int $length = 12): string
    {
        $length = max(10, $length);

        $required = [
            self::pick(self::UPPER),
            self::pick(self::LOWER),
            self::pick(self::DIGIT),
            self::pick(self::SYMBOL),
        ];

        $pool = self::UPPER.self::LOWER.self::DIGIT.self::SYMBOL;

        for ($i = count($required); $i < $length; $i++) {
            $required[] = self::pick($pool);
        }

        // Shuffle so the required characters are not always in the same slots.
        for ($i = count($required) - 1; $i > 0; $i--) {
            $j = random_int(0, $i);
            [$required[$i], $required[$j]] = [$required[$j], $required[$i]];
        }

        return implode('', $required);
    }

    private static function pick(string $alphabet): string
    {
        return $alphabet[random_int(0, strlen($alphabet) - 1)];
    }
}
