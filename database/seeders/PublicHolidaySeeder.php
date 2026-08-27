<?php

declare(strict_types=1);

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

/**
 * Public holidays are data, not code — they move each year and differ by state.
 *
 * This seeder deliberately ships only the fixed-date national holidays. The
 * moving ones (Hari Raya, Deepavali, Chinese New Year, Wesak, Thaipusam and the
 * state holidays) must be entered by HR each year from the official gazette,
 * because their dates are announced rather than calculated.
 */
final class PublicHolidaySeeder extends Seeder
{
    public function run(): void
    {
        $year = (int) now()->year;

        $fixedDateHolidays = [
            "{$year}-01-01" => "New Year's Day",
            "{$year}-05-01" => 'Labour Day',
            "{$year}-08-31" => 'National Day',
            "{$year}-09-16" => 'Malaysia Day',
            "{$year}-12-25" => 'Christmas Day',
        ];

        foreach ($fixedDateHolidays as $date => $name) {
            DB::table('public_holidays')->updateOrInsert(
                ['date' => $date, 'region' => null],
                ['name' => $name, 'created_at' => now(), 'updated_at' => now()],
            );
        }

        $this->command?->warn(
            'Seeded fixed-date holidays only. Add this year\'s gazetted moving '
            .'holidays and any state holidays before employees apply for leave.'
        );
    }
}
