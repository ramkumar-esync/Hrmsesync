<?php

declare(strict_types=1);

namespace HR\Leave\Infrastructure\Persistence\Eloquent;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;

final class LeaveTypeRecord extends Model
{
    use HasUuids;

    protected $table = 'leave_types';

    public $incrementing = false;

    protected $keyType = 'string';

    protected $guarded = [];

    protected function casts(): array
    {
        return [
            'is_paid' => 'boolean',
            'allow_half_day' => 'boolean',
            'requires_attachment' => 'boolean',
            'allow_backdating' => 'boolean',
            'is_active' => 'boolean',
            'default_entitlement_days' => 'float',
            'carry_forward_cap' => 'float',
        ];
    }
}
