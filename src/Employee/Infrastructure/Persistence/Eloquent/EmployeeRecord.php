<?php

declare(strict_types=1);

namespace HR\Employee\Infrastructure\Persistence\Eloquent;

use HR\Identity\Infrastructure\Persistence\Eloquent\User;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Persistence model only. It carries no business rules — those live in
 * HR\Employee\Domain\Entity\Employee.
 *
 * @property string $id
 * @property string $employee_number
 * @property int $basic_salary_minor
 */
final class EmployeeRecord extends Model
{
    use HasUuids;

    protected $table = 'employees';

    public $incrementing = false;

    protected $keyType = 'string';

    protected $guarded = [];

    protected function casts(): array
    {
        return [
            'joined_on' => 'immutable_date',
            'left_on' => 'immutable_date',
            'date_of_birth' => 'immutable_date',
            'is_citizen' => 'boolean',
            'epf_applicable' => 'boolean',
            'socso_applicable' => 'boolean',
            'eis_applicable' => 'boolean',
            'basic_salary_minor' => 'integer',
            'fixed_allowance_minor' => 'integer',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    public function manager(): BelongsTo
    {
        return $this->belongsTo(self::class, 'reports_to');
    }
}
