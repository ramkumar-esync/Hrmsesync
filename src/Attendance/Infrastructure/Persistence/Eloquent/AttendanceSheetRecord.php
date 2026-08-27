<?php

declare(strict_types=1);

namespace HR\Attendance\Infrastructure\Persistence\Eloquent;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;

/**
 * @property string $id
 * @property string $employee_id
 * @property string $period
 * @property string $status
 * @property array $entries
 * @property int $total_minutes
 */
final class AttendanceSheetRecord extends Model
{
    use HasUuids;

    protected $table = 'attendance_sheets';

    protected $keyType = 'string';

    public $incrementing = false;

    protected $guarded = [];

    protected function casts(): array
    {
        return [
            'entries' => 'array',
            'total_minutes' => 'integer',
            'submitted_at' => 'immutable_datetime',
            'decided_at' => 'immutable_datetime',
        ];
    }
}
