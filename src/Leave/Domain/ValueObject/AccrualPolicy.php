<?php

declare(strict_types=1);

namespace HR\Leave\Domain\ValueObject;

enum AccrualPolicy: string
{
    /** Full year's entitlement granted on 1 January (or on joining, pro-rated). */
    case AnnualGrant = 'annual_grant';

    /** Days accrue each completed month of service. */
    case MonthlyAccrual = 'monthly_accrual';

    /** No fixed entitlement — sick leave certified by a doctor, for example. */
    case Unlimited = 'unlimited';

    public function label(): string
    {
        return match ($this) {
            self::AnnualGrant => 'Granted annually',
            self::MonthlyAccrual => 'Accrued monthly',
            self::Unlimited => 'No fixed entitlement',
        };
    }
}
