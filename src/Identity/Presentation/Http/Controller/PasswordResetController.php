<?php

declare(strict_types=1);

namespace HR\Identity\Presentation\Http\Controller;

use HR\Employee\Domain\Repository\EmployeeRepository;
use HR\Employee\Domain\ValueObject\EmployeeId;
use HR\Identity\Application\Command\ResetEmployeePassword;
use HR\Identity\Application\Command\ResetEmployeePasswordHandler;
use App\Mail\PasswordResetMail;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;

/**
 * Resets an employee's login password to a fresh temporary one.
 *
 * The route sits behind role:manager,hr_admin. Within that, this method draws
 * the finer line: an HR admin may reset anyone, while a manager may reset only
 * the people who report to them. That "direct report" check cannot live in
 * route middleware because it depends on the target employee, so it is enforced
 * here before the command runs.
 */
final class PasswordResetController
{
    public function reset(
        string $employeeId,
        Request $request,
        EmployeeRepository $employees,
        ResetEmployeePasswordHandler $handler,
    ): JsonResponse {
        $actor = $request->user();
 
        $this->assertMayReset($actor, $employeeId, $employees);
 
        $temporaryPassword = $handler(new ResetEmployeePassword($employeeId));
 
        // Email the employee their new temporary password. Never let a mail
        // failure fail the reset — HR still has the password on screen.
        $employee = $employees->find(EmployeeId::fromString($employeeId));
        if ($employee !== null) {
            try {
                Mail::to($employee->workEmail())->send(new PasswordResetMail(
                    name: $employee->name()->full,
                    email: $employee->workEmail(),
                    appUrl: (string) config('app.url'),
                    temporaryPassword: $temporaryPassword,
                ));
            } catch (\Throwable $e) {
                Log::warning('Password reset email could not be sent.', [
                    'employee' => $employeeId,
                    'error' => $e->getMessage(),
                ]);
            }
        }
 
        return response()->json([
            'message' => 'Password reset. Share this once — it will not be shown again.',
            'data' => ['temporary_password' => $temporaryPassword],
        ]);
    }


    private function assertMayReset(
        object $actor,
        string $targetEmployeeId,
        EmployeeRepository $employees,
    ): void {
        // HR administrators can reset any account.
        if ($actor->role->canApproveLeaveForAnyone()) {
            return;
        }

        // A manager can reset only their own direct reports.
        $actorEmployeeId = $actor->employeeId();

        if ($actorEmployeeId !== null) {
            $reports = $employees->directReportsOf(EmployeeId::fromString($actorEmployeeId));

            foreach ($reports as $report) {
                if ($report->value === $targetEmployeeId) {
                    return;
                }
            }
        }

        throw new AccessDeniedHttpException('You can only reset the password of someone who reports to you.');
    }
}
