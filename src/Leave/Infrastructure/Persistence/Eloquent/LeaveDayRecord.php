<?php

declare(strict_types=1);

namespace HR\Leave\Infrastructure\Persistence\Eloquent;

use Illuminate\Database\Eloquent\Model;

/**
 * One row per calendar day of an application. This table is what lets payroll
 * split a leave spell across two pay periods exactly.
 */
final class LeaveDayRecord extends Model
{
    protected $table = 'leave_days';

    public $timestamps = false;

    protected $guarded = [];

    protected function casts(): array
    {
        return [
            'date' => 'immutable_date',
            'is_deductible' => 'boolean',
        ];
    }
}
