<?php

declare(strict_types=1);

namespace HR\Payroll\Infrastructure\Persistence\Eloquent;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

final class PayrollRunRecord extends Model
{
    use HasUuids;

    protected $table = 'payroll_runs';

    public $incrementing = false;

    protected $keyType = 'string';

    protected $guarded = [];

    protected function casts(): array
    {
        return [
            'payment_date' => 'immutable_date',
            'finalised_at' => 'immutable_datetime',
            'paid_at' => 'immutable_datetime',
            'payslip_count' => 'integer',
            'total_net_pay_minor' => 'integer',
            'total_employer_cost_minor' => 'integer',
        ];
    }

    public function payslips(): HasMany
    {
        return $this->hasMany(PayslipRecord::class, 'payroll_run_id');
    }
}
