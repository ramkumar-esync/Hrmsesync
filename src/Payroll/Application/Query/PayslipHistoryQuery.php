<?php

declare(strict_types=1);

namespace HR\Payroll\Application\Query;

use Illuminate\Database\DatabaseManager;
use Illuminate\Support\Collection;

/** Read model for "my payslips" and the HR payslip register. */
final readonly class PayslipHistoryQuery
{
    public function __construct(private DatabaseManager $database) {}

    /** @return Collection<int, object> */
    public function forEmployee(string $employeeId, ?int $year = null): Collection
    {
        return $this->database->table('payslips')
            ->select([
                'id', 'period', 'currency', 'gross_pay_minor',
                'total_deductions_minor', 'net_pay_minor', 'issued_at',
                'document_path', 'status',
            ])
            ->where('employee_id', $employeeId)
            ->whereIn('status', ['issued', 'superseded'])
            ->when($year, fn ($query) => $query->where('period', 'like', "{$year}-%"))
            ->orderByDesc('period')
            ->get()
            ->map(fn (object $row) => $this->present($row));
    }

    /** @return Collection<int, object> */
    public function forRun(string $runId): Collection
    {
        return $this->database->table('payslips')
            ->where('payroll_run_id', $runId)
            ->orderBy('employee_snapshot->employee_number')
            ->get()
            ->map(function (object $row): object {
                $presented = $this->present($row);
                $snapshot = json_decode((string) $row->employee_snapshot, true);
                $presented->employee_number = $snapshot['employee_number'] ?? null;
                $presented->employee_name = $snapshot['name'] ?? null;

                return $presented;
            });
    }

    /** Year-to-date totals, the figure employees ask HR for most often. */
    public function yearToDateTotals(string $employeeId, int $year): object
    {
        $row = $this->database->table('payslips')
            ->selectRaw('COUNT(*) as payslip_count')
            ->selectRaw('COALESCE(SUM(gross_pay_minor), 0) as gross_minor')
            ->selectRaw('COALESCE(SUM(total_deductions_minor), 0) as deductions_minor')
            ->selectRaw('COALESCE(SUM(net_pay_minor), 0) as net_minor')
            ->where('employee_id', $employeeId)
            ->where('status', 'issued')
            ->where('period', 'like', "{$year}-%")
            ->first();

        return (object) [
            'year' => $year,
            'payslip_count' => (int) ($row->payslip_count ?? 0),
            'gross' => $this->decimal((int) ($row->gross_minor ?? 0)),
            'deductions' => $this->decimal((int) ($row->deductions_minor ?? 0)),
            'net' => $this->decimal((int) ($row->net_minor ?? 0)),
        ];
    }

    private function present(object $row): object
    {
        return (object) [
            'id' => $row->id,
            'period' => $row->period,
            'currency' => $row->currency,
            'gross_pay' => $this->decimal((int) $row->gross_pay_minor),
            'total_deductions' => $this->decimal((int) $row->total_deductions_minor),
            'net_pay' => $this->decimal((int) $row->net_pay_minor),
            'issued_at' => $row->issued_at,
            'status' => $row->status,
            'document_ready' => $row->document_path !== null,
        ];
    }

    private function decimal(int $minorUnits): string
    {
        return number_format($minorUnits / 100, 2, '.', '');
    }
}
