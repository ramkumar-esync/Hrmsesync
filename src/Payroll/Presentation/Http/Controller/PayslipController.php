<?php

declare(strict_types=1);

namespace HR\Payroll\Presentation\Http\Controller;

use HR\Employee\Domain\Repository\EmployeeRepository;
use HR\Payroll\Application\Query\PayslipHistoryQuery;
use HR\Payroll\Domain\Repository\PayslipRepository;
use HR\Payroll\Domain\Service\PayslipRenderer;
use HR\Payroll\Domain\ValueObject\PayslipId;
use HR\Payroll\Presentation\Http\Resource\PayslipResource;
use Illuminate\Contracts\Filesystem\Factory as FilesystemFactory;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * What an employee sees: their own payslips, and the download.
 *
 * Documents are never served from a public path. Each download is authorised
 * per request and streamed from the private disk, so a leaked URL is useless
 * without the caller's own session.
 */
final class PayslipController
{
    public function mine(
        Request $request,
        EmployeeRepository $employees,
        PayslipHistoryQuery $query,
    ): JsonResponse {
        $employee = $employees->findByUserId((string) $request->user()->id);

        if ($employee === null) {
            return response()->json([
                'message' => 'This account is not linked to an employee record. Contact HR.',
            ], 404);
        }

        $year = $request->has('year') ? (int) $request->query('year') : null;

        return response()->json([
            'data' => $query->forEmployee($employee->id->value, $year),
            'year_to_date' => $query->yearToDateTotals(
                $employee->id->value,
                $year ?? (int) now()->year,
            ),
        ]);
    }

    public function show(string $payslipId, PayslipRepository $payslips): PayslipResource
    {
        $payslip = $payslips->get(PayslipId::fromString($payslipId));

        Gate::authorize('view', $payslip);

        return new PayslipResource($payslip);
    }

    public function download(
        string $payslipId,
        PayslipRepository $payslips,
        PayslipRenderer $renderer,
        FilesystemFactory $filesystem,
    ): StreamedResponse {
        $payslip = $payslips->get(PayslipId::fromString($payslipId));

        Gate::authorize('download', $payslip);

        $disk = $filesystem->disk(config('payroll.payslips.disk'));
        $path = $payslip->documentPath();

        // The queued renderer may not have caught up, or the file may have been
        // pruned. Regenerate on demand rather than showing the employee an error.
        if ($path === null || ! $disk->exists($path)) {
            $path = $renderer->render($payslip);
            $payslip->attachDocument($path);
            $payslips->save($payslip);
        }

        $filename = sprintf(
            'Payslip-%s-%s.pdf',
            $payslip->period,
            $payslip->employee()->employeeNumber,
        );

        return $disk->download($path, $filename, [
            'Cache-Control' => 'no-store, private',
        ]);
    }
}
