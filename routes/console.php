<?php

use HR\Leave\Application\Command\GrantAnnualEntitlements;
use HR\Leave\Application\Command\GrantAnnualEntitlementsHandler;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('leave:grant-entitlements {year?}', function (GrantAnnualEntitlementsHandler $handler) {
    $year = (int) ($this->argument('year') ?? now()->year);
    $granted = $handler(new GrantAnnualEntitlements($year));

    $this->info("Granted or refreshed {$granted} leave entitlement records for {$year}.");
})->purpose('Grant leave entitlements for a calendar year');

Schedule::command('leave:grant-entitlements')->yearlyOn(1, 1, '00:30');
