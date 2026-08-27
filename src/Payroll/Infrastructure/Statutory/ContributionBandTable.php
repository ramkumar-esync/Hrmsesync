<?php

declare(strict_types=1);

namespace HR\Payroll\Infrastructure\Statutory;

use HR\Shared\Domain\ValueObject\Money;

/**
 * A banded contribution table: "wages up to X → employee pays A, employer pays B".
 *
 * SOCSO and EIS are published this way rather than as percentages, and the
 * published amounts are what auditors check against. This class exists so the
 * official tables can be dropped into config verbatim.
 */
final readonly class ContributionBandTable
{
    /** @param list<array{up_to: string|null, employee: string, employer: string}> $bands */
    private function __construct(private array $bands, private string $currency) {}

    /** @param array<int, array<string, mixed>> $bands */
    public static function fromConfig(array $bands, string $currency): self
    {
        $normalised = array_map(static fn (array $band): array => [
            'up_to' => $band['up_to'] === null ? null : (string) $band['up_to'],
            'employee' => (string) $band['employee'],
            'employer' => (string) $band['employer'],
        ], array_values($bands));

        usort($normalised, static function (array $a, array $b): int {
            if ($a['up_to'] === null) {
                return 1;
            }

            if ($b['up_to'] === null) {
                return -1;
            }

            return (float) $a['up_to'] <=> (float) $b['up_to'];
        });

        return new self($normalised, $currency);
    }

    /** @return array{employee: Money, employer: Money} */
    public function lookup(Money $wages): array
    {
        foreach ($this->bands as $band) {
            $ceiling = $band['up_to'] === null
                ? null
                : Money::fromDecimal($band['up_to'], $this->currency);

            if ($ceiling === null || ! $wages->isGreaterThan($ceiling)) {
                return [
                    'employee' => Money::fromDecimal($band['employee'], $this->currency),
                    'employer' => Money::fromDecimal($band['employer'], $this->currency),
                ];
            }
        }

        return [
            'employee' => Money::zero($this->currency),
            'employer' => Money::zero($this->currency),
        ];
    }

    public function isEmpty(): bool
    {
        return $this->bands === [];
    }
}
