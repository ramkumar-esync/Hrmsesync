<?php

declare(strict_types=1);

namespace Database\Seeders;

use Illuminate\Database\Seeder;

final class DatabaseSeeder extends Seeder
{
    public function run(): void
    {
        // Order matters: leave types must exist before entitlements are granted.
        $this->call([
            LeaveTypeSeeder::class,
            PublicHolidaySeeder::class,
            DemoDataSeeder::class,
        ]);
    }
}
