<?php

declare(strict_types=1);

namespace HR\Leave\Presentation\Http\Controller;

use Illuminate\Database\DatabaseManager;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Public holidays for the calendar. Read-only and available to any signed-in
 * user — everyone's dashboard shows the same holidays. Optionally filtered by
 * year and by region (a null region means a national holiday, which everyone
 * sees regardless of the region filter).
 */
final class HolidayController
{
    public function index(Request $request, DatabaseManager $database): JsonResponse
    {
        $year = (int) $request->integer('year', (int) now()->year);
        $region = $request->query('region');

        $holidays = $database->table('public_holidays')
            ->whereYear('date', $year)
            ->when(
                is_string($region) && $region !== '',
                // National holidays (region null) plus this region's own.
                fn ($q) => $q->where(function ($inner) use ($region): void {
                    $inner->whereNull('region')->orWhere('region', $region);
                }),
            )
            ->orderBy('date')
            ->get(['date', 'name', 'region'])
            ->map(static fn ($row) => [
                'date' => \Carbon\CarbonImmutable::parse($row->date)->toDateString(),
                'name' => $row->name,
                'region' => $row->region,
            ])
            ->all();

        return response()->json(['data' => $holidays]);
    }
}