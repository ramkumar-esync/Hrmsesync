<?php

declare(strict_types=1);

namespace HR\Leave\Infrastructure\Persistence\Eloquent;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;

final class LeaveEntitlementRecord extends Model
{
    use HasUuids;

    protected $table = 'leave_entitlements';

    public $incrementing = false;

    protected $keyType = 'string';

    protected $guarded = [];

    protected function casts(): array
    {
        return [
            'year' => 'integer',
            'entitled_days' => 'float',
            'carried_forward_days' => 'float',
            'adjustment_days' => 'float',
            'taken_days' => 'float',
            'pending_days' => 'float',
        ];
    }
}
