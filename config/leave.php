<?php

return [
    /*
     * Non-working days of the week, as ISO day numbers (1 = Monday).
     * A six-day week would be [7] for Sunday only.
     */
    'rest_days' => array_values(array_filter(array_map(
        static fn (string $day) => match (strtolower(trim($day))) {
            'monday' => 1, 'tuesday' => 2, 'wednesday' => 3, 'thursday' => 4,
            'friday' => 5, 'saturday' => 6, 'sunday' => 7,
            default => null,
        },
        explode(',', (string) env('LEAVE_REST_DAYS', 'saturday,sunday')),
    ))),

    /*
     * How many days late an employee may still file leave themselves, for leave
     * types that allow backdating at all (sick leave, typically). Beyond this,
     * HR records it on their behalf.
     */
    'backdate_grace_days' => (int) env('LEAVE_BACKDATE_GRACE_DAYS', 7),

    /*
     * Public holidays can differ by state. Set this to scope the holiday
     * calendar; null uses national holidays only.
     */
    'holiday_region' => env('LEAVE_HOLIDAY_REGION'),
];
