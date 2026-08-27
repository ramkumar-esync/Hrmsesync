<?php

declare(strict_types=1);

namespace App\Support;

use App\Models\AppNotification;
use Illuminate\Support\Facades\Log;

/**
 * One place to raise an in-app notification, so the controllers that trigger
 * them stay short. Best-effort: a failure here is logged, never thrown, so
 * notifying can never break the action that caused it.
 */
final class Notifier
{
    public static function push(
        ?string $userId,
        string $type,
        string $title,
        ?string $body = null,
        ?string $actionUrl = null,
    ): void {
        if ($userId === null || $userId === '') {
            return;
        }

        try {
            AppNotification::query()->create([
                'user_id' => $userId,
                'type' => $type,
                'title' => $title,
                'body' => $body,
                'action_url' => $actionUrl,
            ]);
        } catch (\Throwable $e) {
            Log::warning('Could not create notification.', [
                'user' => $userId,
                'type' => $type,
                'error' => $e->getMessage(),
            ]);
        }
    }
}
