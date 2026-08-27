<?php

declare(strict_types=1);

namespace HR\Leave\Domain\ValueObject;

/**
 * The numbers an employee sees on their balance screen.
 *
 * "Available" is deliberately net of pending applications — showing a balance
 * that already counts days you have asked for is the fastest way to get an
 * employee overdrawn and an approver embarrassed.
 */
final readonly class LeaveBalance implements \JsonSerializable
{
    public function __construct(
        public float $entitled,
        public float $carriedForward,
        public float $adjustment,
        public float $taken,
        public float $pending,
    ) {}

    public function granted(): float
    {
        return round($this->entitled + $this->carriedForward + $this->adjustment, 2);
    }

    public function available(): float
    {
        return round($this->granted() - $this->taken - $this->pending, 2);
    }

    public function covers(float $days): bool
    {
        // Guard against float noise on half-days.
        return $this->available() + 0.001 >= $days;
    }

    /** @return array<string, float> */
    public function jsonSerialize(): array
    {
        return [
            'entitled' => $this->entitled,
            'carried_forward' => $this->carriedForward,
            'adjustment' => $this->adjustment,
            'granted' => $this->granted(),
            'taken' => $this->taken,
            'pending' => $this->pending,
            'available' => $this->available(),
        ];
    }
}
