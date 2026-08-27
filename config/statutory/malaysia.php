<?php

/*
|------------------------------------------------------------------------------
| Malaysian statutory rates — DEVELOPMENT DEFAULTS, NOT A CERTIFIED RATE SET
|------------------------------------------------------------------------------
|
| The numbers below give the payroll engine a working shape. They are NOT a
| substitute for the official published tables, and they are not maintained in
| step with rate changes.
|
| Before you run real payroll:
|
|   1. Have your payroll or tax adviser reconcile every value here against the
|      current published tables from KWSP (EPF), PERKESO (SOCSO and EIS) and
|      LHDN (PCB/MTD).
|   2. Replace the percentage fallbacks for SOCSO and EIS with the official
|      banded contribution tables. Drop them into the 'bands' arrays and the
|      engine will use them in preference to the percentages — the published
|      amounts are what an audit checks against, and percentages will not match
|      them exactly at every wage level.
|   3. Set 'rate_set_label' to the version you reconciled, so payslips can be
|      traced back to a known rate set.
|   4. Re-run that reconciliation whenever rates change. They change often, and
|      sometimes mid-year.
|
| Getting these wrong means under-remitting statutory contributions, which is
| the employer's liability, not the employee's.
|
*/

return [
    'rate_set_label' => 'UNVERIFIED-DEV-DEFAULTS',

    'epf' => [
        'senior_age' => 60,

        // Above this monthly wage the employer rate steps down.
        'employer_rate_threshold' => '5000.00',

        // Set to null for no ceiling.
        'wage_ceiling' => null,

        'rates' => [
            'standard' => [
                'employee' => 11.0,
                'employer_at_or_below_threshold' => 13.0,
                'employer_above_threshold' => 12.0,
            ],
            'senior_citizen' => [
                'employee' => 0.0,
                'employer_at_or_below_threshold' => 4.0,
                'employer_above_threshold' => 4.0,
            ],
            'non_citizen' => [
                'employee' => 11.0,
                'employer_at_or_below_threshold' => 2.0,
                'employer_above_threshold' => 2.0,
            ],
            'senior_non_citizen' => [
                'employee' => 0.0,
                'employer_at_or_below_threshold' => 2.0,
                'employer_above_threshold' => 2.0,
            ],
        ],
    ],

    'socso' => [
        'wage_ceiling' => '6000.00',
        'employer_only_age' => 60,

        // Percentage fallback — replace with the official banded table below.
        'employee_rate' => 0.5,
        'employer_rate' => 1.75,
        'employer_rate_senior' => 1.25,

        /*
         * Official PERKESO contribution table goes here, e.g.
         *   ['up_to' => '30.00',  'employee' => '0.10', 'employer' => '0.40'],
         *   ['up_to' => '50.00',  'employee' => '0.20', 'employer' => '0.70'],
         *   ...
         *   ['up_to' => null,     'employee' => '19.75','employer' => '69.05'],
         * When this array is non-empty the engine uses it instead of the rates.
         */
        'bands' => [],
    ],

    'eis' => [
        'wage_ceiling' => '6000.00',
        'min_age' => 18,
        'max_age' => 60,

        // Percentage fallback — replace with the official banded table below.
        'employee_rate' => 0.2,
        'employer_rate' => 0.2,

        'bands' => [],
    ],

    /*
     * Used only when PCB_ENGINE=progressive_estimate. This is an estimate for
     * sanity-checking a run, not an LHDN MTD calculation, and must not be used
     * to determine what is actually remitted.
     */
    'pcb_estimate' => [
        'individual_relief' => '9000.00',
        'epf_relief_cap' => '4000.00',
        'spouse_relief' => '4000.00',
        'child_relief' => '2000.00',

        // Annual chargeable income bands. Verify against the current year.
        'bands' => [
            ['up_to' => '5000.00', 'rate' => 0.0],
            ['up_to' => '20000.00', 'rate' => 1.0],
            ['up_to' => '35000.00', 'rate' => 3.0],
            ['up_to' => '50000.00', 'rate' => 6.0],
            ['up_to' => '70000.00', 'rate' => 11.0],
            ['up_to' => '100000.00', 'rate' => 19.0],
            ['up_to' => '400000.00', 'rate' => 25.0],
            ['up_to' => '600000.00', 'rate' => 26.0],
            ['up_to' => '2000000.00', 'rate' => 28.0],
            ['up_to' => null, 'rate' => 30.0],
        ],
    ],
];
