<?php

declare(strict_types=1);

namespace HR\Employee\Presentation\Http\Controller;

use HR\Employee\Application\Query\UpcomingBirthdaysQuery;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Upcoming staff birthdays for the HR and manager dashboards. The route group
 * restricts this to those roles; a plain employee never reaches it.
 */
final class BirthdayController
{
    public function upcoming(Request $request, UpcomingBirthdaysQuery $query): JsonResponse
    {
        $days = (int) $request->integer('days', 30);
        $days = max(1, min($days, 90));

        return response()->json(['data' => $query->withinDays($days)]);
    }
}
