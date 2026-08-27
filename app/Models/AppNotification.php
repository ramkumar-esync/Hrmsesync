<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;

/**
 * A simple per-user in-app notification. Deliberately not Laravel's polymorphic
 * notifications — a plain table keyed by user_id is enough here and avoids
 * wiring the Notifiable trait onto the custom User model.
 *
 * @property string $id
 * @property string $user_id
 * @property string $type
 * @property string $title
 * @property ?string $body
 * @property ?string $action_url
 * @property ?\Illuminate\Support\Carbon $read_at
 */
final class AppNotification extends Model
{
    use HasUuids;

    protected $table = 'app_notifications';

    protected $keyType = 'string';

    public $incrementing = false;

    protected $guarded = [];

    protected function casts(): array
    {
        return ['read_at' => 'immutable_datetime'];
    }
}
