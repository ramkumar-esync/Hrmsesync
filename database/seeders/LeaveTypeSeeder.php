<?php

declare(strict_types=1);

namespace Database\Seeders;

use HR\Leave\Domain\Entity\LeaveType;
use HR\Leave\Domain\Repository\LeaveTypeRepository;
use HR\Leave\Domain\ValueObject\AccrualPolicy;
use Illuminate\Database\Seeder;

/**
 * A starting set of leave types.
 *
 * These are configured for a Malaysian employer following the Employment Act as
 * a baseline. Entitlement days vary with length of service and by contract —
 * adjust them to match your own handbook before going live.
 */
final class LeaveTypeSeeder extends Seeder
{
    public function run(LeaveTypeRepository $leaveTypes): void
    {
        $definitions = [
            [
                'code' => 'ANNUAL',
                'name' => 'Annual leave',
                'paid' => true,
                'accrual' => AccrualPolicy::AnnualGrant,
                'days' => 14.0,
                'carry_cap' => 7.0,
                'notice' => 3,
                'half_day' => true,
            ],
            [
                'code' => 'SICK',
                'name' => 'Sick leave',
                'paid' => true,
                'accrual' => AccrualPolicy::AnnualGrant,
                'days' => 14.0,
                'carry_cap' => 0.0,
                'notice' => 0,
                'half_day' => false,
                'attachment' => true,
                'backdate' => true,
            ],
            [
                'code' => 'HOSPITALISATION',
                'name' => 'Hospitalisation leave',
                'paid' => true,
                'accrual' => AccrualPolicy::AnnualGrant,
                'days' => 60.0,
                'carry_cap' => 0.0,
                'notice' => 0,
                'half_day' => false,
                'attachment' => true,
                'backdate' => true,
            ],
            [
                'code' => 'MATERNITY',
                'name' => 'Maternity leave',
                'paid' => true,
                'accrual' => AccrualPolicy::AnnualGrant,
                'days' => 98.0,
                'carry_cap' => 0.0,
                'notice' => 30,
                'half_day' => false,
                'attachment' => true,
            ],
            [
                'code' => 'PATERNITY',
                'name' => 'Paternity leave',
                'paid' => true,
                'accrual' => AccrualPolicy::AnnualGrant,
                'days' => 7.0,
                'carry_cap' => 0.0,
                'notice' => 7,
                'half_day' => false,
            ],
            [
                'code' => 'COMPASSIONATE',
                'name' => 'Compassionate leave',
                'paid' => true,
                'accrual' => AccrualPolicy::AnnualGrant,
                'days' => 3.0,
                'carry_cap' => 0.0,
                'notice' => 0,
                'half_day' => false,
                'backdate' => true,
            ],
            [
                'code' => 'UNPAID',
                'name' => 'Unpaid leave',
                'paid' => false,
                'accrual' => AccrualPolicy::Unlimited,
                'days' => 0.0,
                'carry_cap' => 0.0,
                'notice' => 7,
                'half_day' => true,
            ],
        ];

        foreach ($definitions as $definition) {
            if ($leaveTypes->findByCode($definition['code']) !== null) {
                continue;
            }

            $leaveTypes->save(LeaveType::define(
                code: $definition['code'],
                name: $definition['name'],
                paid: $definition['paid'],
                accrualPolicy: $definition['accrual'],
                defaultEntitlementDays: $definition['days'],
                carryForwardCap: $definition['carry_cap'],
                carryForwardExpiryMonths: 3,
                allowHalfDay: $definition['half_day'],
                requiresAttachment: $definition['attachment'] ?? false,
                minNoticeDays: $definition['notice'],
                allowBackdating: $definition['backdate'] ?? false,
            ));
        }
    }
}
