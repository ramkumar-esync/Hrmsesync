<?php

declare(strict_types=1);

namespace HR\Payroll\Presentation\Http\Controller;

use HR\Payroll\Application\Command\FinalisePayrollRun;
use HR\Payroll\Application\Command\FinalisePayrollRunHandler;
use HR\Payroll\Application\Command\MarkPayrollRunPaid;
use HR\Payroll\Application\Command\MarkPayrollRunPaidHandler;
use HR\Payroll\Application\Command\OpenPayrollRun;
use HR\Payroll\Application\Command\OpenPayrollRunHandler;
use HR\Payroll\Application\Command\PopulateRunWithActiveStaff;
use HR\Payroll\Application\Command\PopulateRunWithActiveStaffHandler;
use HR\Payroll\Application\Command\RecordPayrollEntry;
use HR\Payroll\Application\Command\RecordPayrollEntryHandler;
use HR\Payroll\Application\Command\RemovePayslipFromRun;
use HR\Payroll\Application\Command\RemovePayslipFromRunHandler;
use HR\Payroll\Application\Query\PayslipHistoryQuery;
use HR\Payroll\Domain\Repository\PayrollRunRepository;
use HR\Payroll\Domain\ValueObject\PayrollRunId;
use HR\Payroll\Infrastructure\Persistence\Eloquent\PayrollRunRecord;
use HR\Payroll\Presentation\Http\Request\OpenPayrollRunRequest;
use HR\Payroll\Presentation\Http\Request\RecordPayrollEntryRequest;
use HR\Payroll\Presentation\Http\Resource\PayrollRunResource;
use HR\Payroll\Presentation\Http\Resource\PayslipResource;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/** HR-facing payroll operations. Every route here is behind role:hr_admin. */
final class PayrollRunController
{
    public function index(Request $request): JsonResponse
    {
        return response()->json(
            PayrollRunRecord::query()
                ->when($request->query('status'), fn ($q, $status) => $q->where('status', $status))
                ->orderByDesc('period')
                ->paginate(min((int) $request->query('per_page', 12), 60))
        );
    }

    public function show(string $runId, PayrollRunRepository $runs): PayrollRunResource
    {
        return new PayrollRunResource($runs->get(PayrollRunId::fromString($runId)));
    }

    public function store(OpenPayrollRunRequest $request, OpenPayrollRunHandler $handler): JsonResponse
    {
        $run = $handler(new OpenPayrollRun(
            period: $request->string('period')->value(),
            paymentDate: $request->string('payment_date')->value(),
            openedBy: (string) $request->user()->id,
            notes: $request->input('notes'),
        ));

        return (new PayrollRunResource($run))->response()->setStatusCode(201);
    }

    /** Creates a draft payslip for everyone employed during the period. */
    public function populate(
        string $runId,
        PopulateRunWithActiveStaffHandler $handler,
    ): JsonResponse {
        $created = $handler(new PopulateRunWithActiveStaff($runId));

        return response()->json([
            'message' => "Added {$created} employees to this run.",
            'payslips_created' => $created,
        ]);
    }

    public function payslips(string $runId, PayslipHistoryQuery $query): JsonResponse
    {
        return response()->json(['data' => $query->forRun($runId)]);
    }

    /** Enter or overwrite one employee's pay for this run. */
    public function recordEntry(
        string $runId,
        RecordPayrollEntryRequest $request,
        RecordPayrollEntryHandler $handler,
    ): PayslipResource {
        $payslip = $handler(new RecordPayrollEntry(
            runId: $runId,
            employeeId: $request->string('employee_id')->value(),
            earnings: $request->input('earnings', []),
            deductions: $request->input('deductions', []),
            remarks: $request->input('remarks'),
            useContractualSalary: $request->boolean('use_contractual_salary', true),
        ));

        return new PayslipResource($payslip);
    }

    public function removePayslip(
        string $runId,
        string $payslipId,
        RemovePayslipFromRunHandler $handler,
    ): JsonResponse {
        $handler(new RemovePayslipFromRun($runId, $payslipId));

        return response()->json(['message' => 'Payslip removed from the run.']);
    }

    /**
     * Locks the run and issues every payslip. Irreversible — the UI should
     * confirm the totals with the user before calling this.
     */
    public function finalise(
        string $runId,
        Request $request,
        FinalisePayrollRunHandler $handler,
    ): PayrollRunResource {
        return new PayrollRunResource($handler(new FinalisePayrollRun(
            runId: $runId,
            finalisedBy: (string) $request->user()->id,
        )));
    }

    public function markPaid(
        string $runId,
        Request $request,
        MarkPayrollRunPaidHandler $handler,
    ): PayrollRunResource {
        $request->validate(['paid_at' => ['nullable', 'date']]);

        return new PayrollRunResource($handler(
            new MarkPayrollRunPaid($runId, $request->input('paid_at'))
        ));
    }
}
