<?php

declare(strict_types=1);

namespace HR\Leave\Infrastructure\Persistence\Eloquent;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

final class LeaveApplicationRecord extends Model
{
    use HasUuids;

    protected $table = 'leave_applications';

    public $incrementing = false;

    protected $keyType = 'string';

    protected $guarded = [];

    protected function casts(): array
    {
        return [
            'start_date' => 'immutable_date',
            'end_date' => 'immutable_date',
            'applied_at' => 'immutable_datetime',
            'decided_at' => 'immutable_datetime',
            'working_days' => 'float',
            'entitlement_year' => 'integer',
        ];
    }

    public function days(): HasMany
    {
        return $this->hasMany(LeaveDayRecord::class, 'leave_application_id');
    }
}
