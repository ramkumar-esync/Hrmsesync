<?php

declare(strict_types=1);

namespace HR\Identity\Presentation\Http\Controller;

use App\Models\AppNotification;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * The signed-in user's in-app notifications: list the recent ones with an unread
 * count, and mark them read so they don't resurface on the next login.
 */
final class NotificationController
{
    public function index(Request $request): JsonResponse
    {
        $userId = (string) $request->user()->id;

        $items = AppNotification::query()
            ->where('user_id', $userId)
            ->orderByDesc('created_at')
            ->limit(30)
            ->get(['id', 'type', 'title', 'body', 'action_url', 'read_at', 'created_at']);

        $unread = AppNotification::query()
            ->where('user_id', $userId)
            ->whereNull('read_at')
            ->count();

        return response()->json([
            'data' => $items->map(static fn (AppNotification $n) => [
                'id' => $n->id,
                'type' => $n->type,
                'title' => $n->title,
                'body' => $n->body,
                'action_url' => $n->action_url,
                'read' => $n->read_at !== null,
                'created_at' => $n->created_at?->toIso8601String(),
            ]),
            'meta' => ['unread' => $unread],
        ]);
    }

    /**
     * Mark notifications read. With no ids given, marks all of the user's unread
     * ones — the natural "I've seen these" action when the panel is opened.
     */
    public function markRead(Request $request): JsonResponse
    {
        $userId = (string) $request->user()->id;
        $ids = $request->input('ids');

        $query = AppNotification::query()
            ->where('user_id', $userId)
            ->whereNull('read_at');

        if (is_array($ids) && $ids !== []) {
            $query->whereIn('id', $ids);
        }

        $query->update(['read_at' => now()]);

        return response()->json(['message' => 'Marked read.']);
    }
}
