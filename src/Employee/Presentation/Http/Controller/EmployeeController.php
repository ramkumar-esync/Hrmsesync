<?php

declare(strict_types=1);

namespace HR\Employee\Presentation\Http\Controller;

use HR\Employee\Application\Command\ChangeEmploymentStatus;
use HR\Employee\Application\Command\ChangeEmploymentStatusHandler;
use HR\Employee\Application\Command\RegisterEmployee;
use HR\Employee\Application\Command\RegisterEmployeeHandler;
use HR\Employee\Application\Command\TerminateEmployment;
use HR\Employee\Application\Command\TerminateEmploymentHandler;
use HR\Employee\Application\Command\UpdateCompensation;
use HR\Employee\Application\Command\UpdateCompensationHandler;
use HR\Employee\Application\Command\UpdateEmployeeProfile;
use HR\Employee\Application\Command\UpdateEmployeeProfileHandler;
use HR\Employee\Application\DTO\RegisterEmployeeData;
use HR\Employee\Application\Query\EmployeeDirectoryQuery;
use HR\Employee\Domain\Repository\EmployeeRepository;
use HR\Employee\Domain\ValueObject\EmployeeId;
use HR\Employee\Presentation\Http\Request\RegisterEmployeeRequest;
use HR\Employee\Presentation\Http\Request\UpdateCompensationRequest;
use HR\Employee\Presentation\Http\Resource\EmployeeResource;
use HR\Employee\Presentation\Http\Request\UpdateEmployeeProfileRequest;
use App\Mail\EmployeeWelcomeMail;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;

final class EmployeeController
{
    public function index(Request $request, EmployeeDirectoryQuery $directory): JsonResponse
    {
        return response()->json($directory->paginate(
            search: $request->query('search'),
            status: $request->query('status'),
            perPage: min((int) $request->query('per_page', 25), 100),
        ));
    }

    public function show(string $employeeId, EmployeeRepository $employees): EmployeeResource
    {
        return new EmployeeResource($employees->get(EmployeeId::fromString($employeeId)));
    }

    public function store(RegisterEmployeeRequest $request, RegisterEmployeeHandler $handler): JsonResponse
    {
        $result = $handler(new RegisterEmployee(
            RegisterEmployeeData::fromArray($request->validated())
        ));
 
        // Send a welcome email now that the record is committed. The password is
        // included only when a login account was created — otherwise there is
        // nothing to sign in with yet. Mail failure must never fail the request:
        // the employee already exists and HR still has the password on screen.
        try {
            Mail::to($result->employee->workEmail())->send(new EmployeeWelcomeMail(
                name: $result->employee->name()->full,
                email: $result->employee->workEmail(),
                appUrl: (string) config('app.url'),
                temporaryPassword: $result->temporaryPassword,
            ));
        } catch (\Throwable $e) {
            Log::warning('Welcome email could not be sent.', [
                'employee' => $result->employee->employeeNumber()->value,
                'error' => $e->getMessage(),
            ]);
        }
 
        // The temporary password is returned exactly once, here, for HR to pass
        // on. It is never stored in readable form and cannot be fetched again.
        return (new EmployeeResource($result->employee))
            ->additional(['meta' => ['temporary_password' => $result->temporaryPassword]])
            ->response()
            ->setStatusCode(201);
    }

    public function changeStatus(
        string $employeeId,
        Request $request,
        ChangeEmploymentStatusHandler $handler,
    ): EmployeeResource {
        $validated = $request->validate([
            'status' => ['required', 'in:probation,confirmed,contract,resigned,terminated'],
            // Only used when the new status ends employment; it is the last day.
            'effective_on' => ['nullable', 'date'],
        ]);

        return new EmployeeResource($handler(new ChangeEmploymentStatus(
            employeeId: $employeeId,
            status: $validated['status'],
            effectiveOn: $validated['effective_on'] ?? null,
        )));
    }

    public function updateProfile(
        string $employeeId,
        UpdateEmployeeProfileRequest $request,
        UpdateEmployeeProfileHandler $handler,
    ): EmployeeResource {
        return new EmployeeResource($handler(new UpdateEmployeeProfile(
            employeeId: $employeeId,
            name: $request->validated('name'),
            jobTitle: $request->validated('job_title'),
            department: $request->validated('department'),
            bankName: $request->validated('bank_name'),
            bankAccountNumber: $request->validated('bank_account_number'),
        )));
    }

    public function updateCompensation(
        string $employeeId,
        UpdateCompensationRequest $request,
        UpdateCompensationHandler $handler,
    ): EmployeeResource {
        return new EmployeeResource($handler(new UpdateCompensation(
            employeeId: $employeeId,
            basicSalary: $request->string('basic_salary')->value(),
            fixedAllowance: $request->input('fixed_allowance'),
        )));
    }

    public function terminate(
        string $employeeId,
        Request $request,
        TerminateEmploymentHandler $handler,
    ): JsonResponse {
        $validated = $request->validate([
            'last_day' => ['required', 'date'],
            'reason' => ['required', 'in:resigned,terminated'],
        ]);

        $handler(new TerminateEmployment($employeeId, $validated['last_day'], $validated['reason']));

        return response()->json(['message' => 'Employment record closed.']);
    }
}
