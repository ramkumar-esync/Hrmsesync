<?php

declare(strict_types=1);

namespace HR\Payroll\Infrastructure\Persistence\Eloquent;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

final class PayslipRecord extends Model
{
    use HasUuids;

    protected $table = 'payslips';

    public $incrementing = false;

    protected $keyType = 'string';

    protected $guarded = [];

    protected function casts(): array
    {
        return [
            'employee_snapshot' => 'array',
            'earnings' => 'array',
            'deductions' => 'array',
            'employer_contributions' => 'array',
            'issued_at' => 'immutable_datetime',
            'gross_pay_minor' => 'integer',
            'total_deductions_minor' => 'integer',
            'net_pay_minor' => 'integer',
            'employer_cost_minor' => 'integer',
        ];
    }

    public function run(): BelongsTo
    {
        return $this->belongsTo(PayrollRunRecord::class, 'payroll_run_id');
    }
}
