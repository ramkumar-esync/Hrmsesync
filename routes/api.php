<?php

declare(strict_types=1);

use HR\Attendance\Presentation\Http\Controller\AttendanceController;
use HR\Attendance\Presentation\Http\Controller\AttendanceReviewController;
use HR\Employee\Presentation\Http\Controller\BirthdayController;
use HR\Employee\Presentation\Http\Controller\EmployeeController;
use HR\Identity\Presentation\Http\Controller\AuthController;
use HR\Identity\Presentation\Http\Controller\NotificationController;
use HR\Identity\Presentation\Http\Controller\PasswordResetController;
use HR\Leave\Presentation\Http\Controller\LeaveApplicationController;
use HR\Leave\Presentation\Http\Controller\LeaveApprovalController;
use HR\Leave\Presentation\Http\Controller\LeaveBalanceController;
use HR\Leave\Presentation\Http\Controller\HolidayController;
use HR\Payroll\Presentation\Http\Controller\PayrollRunController;
use HR\Payroll\Presentation\Http\Controller\PayslipController;
use Illuminate\Support\Facades\Route;

/*
|------------------------------------------------------------------------------
| API routes
|------------------------------------------------------------------------------
|
| Grouped by who they are for rather than by entity, because that is how access
| actually differs: what an employee can reach, what an approver can reach, and
| what HR can reach.
|
*/

Route::post('auth/login', [AuthController::class, 'login'])->name('auth.login');

Route::middleware('auth:sanctum')->group(function (): void {
    Route::post('auth/logout', [AuthController::class, 'logout'])->name('auth.logout');
    Route::get('auth/me', [AuthController::class, 'me'])->name('auth.me');
    Route::get('holidays', [HolidayController::class, 'index'])->name('holidays.index');
    Route::get('notifications', [NotificationController::class, 'index'])->name('notifications.index');
    Route::post('notifications/read', [NotificationController::class, 'markRead'])->name('notifications.read');
    Route::post('auth/change-password', [AuthController::class, 'changePassword'])->name('auth.change-password');
    /*
     |--------------------------------------------------------------------------
     | Employee self-service
     |--------------------------------------------------------------------------
     */
    Route::prefix('me')->name('me.')->group(function (): void {
        // Payslips
        Route::get('payslips', [PayslipController::class, 'mine'])->name('payslips');

        // Leave
        Route::get('leave/balances', [LeaveBalanceController::class, 'mine'])->name('leave.balances');
        Route::get('leave/applications', [LeaveApplicationController::class, 'index'])->name('leave.applications');

        // Attendance — the employee's own monthly sheet
        Route::get('attendance', [AttendanceController::class, 'show'])->name('attendance.show');
        Route::post('attendance', [AttendanceController::class, 'save'])->name('attendance.save');
        Route::post('attendance/submit', [AttendanceController::class, 'submit'])->name('attendance.submit');
    });

    Route::get('payslips/{payslip}', [PayslipController::class, 'show'])->name('payslips.show');
    Route::get('payslips/{payslip}/download', [PayslipController::class, 'download'])->name('payslips.download');

    Route::prefix('leave')->name('leave.')->group(function (): void {
        Route::get('types', [LeaveBalanceController::class, 'types'])->name('types');
        Route::post('applications', [LeaveApplicationController::class, 'store'])->name('apply');
        Route::get('applications/{application}', [LeaveApplicationController::class, 'show'])->name('show');
        Route::post('applications/{application}/cancel', [LeaveApplicationController::class, 'cancel'])->name('cancel');
    });

    /*
     |--------------------------------------------------------------------------
     | Approvers — managers and HR
     |--------------------------------------------------------------------------
     */
    Route::middleware('role:manager,hr_admin')->prefix('approvals')->name('approvals.')->group(function (): void {
        Route::get('leave', [LeaveApprovalController::class, 'pending'])->name('leave.pending');
        Route::post('leave/{application}', [LeaveApprovalController::class, 'decide'])->name('leave.decide');
        Route::get('leave/calendar', [LeaveApprovalController::class, 'calendar'])->name('leave.calendar');
    });

    /*
     | Password resets. HR resets anyone; a manager resets their direct reports.
     | The finer HR-vs-manager distinction is enforced inside the controller,
     | since it depends on who the target employee is.
     */
    Route::middleware('role:manager,hr_admin')
        ->post('employees/{employee}/reset-password', [PasswordResetController::class, 'reset'])
        ->name('employees.reset-password');

    // Upcoming staff birthdays for the manager/HR dashboards.
    Route::middleware('role:manager,hr_admin')
        ->get('birthdays', [BirthdayController::class, 'upcoming'])
        ->name('birthdays.upcoming');

    /*
     |--------------------------------------------------------------------------
     | HR administration
     |--------------------------------------------------------------------------
     */
    Route::middleware('role:hr_admin')->prefix('hr')->name('hr.')->group(function (): void {
        // Employee records
        Route::get('employees', [EmployeeController::class, 'index'])->name('employees.index');
        Route::post('employees', [EmployeeController::class, 'store'])->name('employees.store');
        Route::get('employees/{employee}', [EmployeeController::class, 'show'])->name('employees.show');
        Route::put('employees/{employee}/compensation', [EmployeeController::class, 'updateCompensation'])
            ->name('employees.compensation');
        Route::post('employees/{employee}/terminate', [EmployeeController::class, 'terminate'])
            ->name('employees.terminate');
        Route::put('employees/{employee}/status', [EmployeeController::class, 'changeStatus'])
            ->name('employees.status');
        Route::put('employees/{employee}/profile', [EmployeeController::class, 'updateProfile'])
            ->name('employees.profile');

        // Payroll runs
        Route::get('payroll-runs', [PayrollRunController::class, 'index'])->name('runs.index');
        Route::post('payroll-runs', [PayrollRunController::class, 'store'])->name('runs.store');
        Route::get('payroll-runs/{run}', [PayrollRunController::class, 'show'])->name('runs.show');
        Route::post('payroll-runs/{run}/populate', [PayrollRunController::class, 'populate'])->name('runs.populate');
        Route::get('payroll-runs/{run}/payslips', [PayrollRunController::class, 'payslips'])->name('runs.payslips');
        Route::post('payroll-runs/{run}/entries', [PayrollRunController::class, 'recordEntry'])->name('runs.entries');
        Route::delete('payroll-runs/{run}/payslips/{payslip}', [PayrollRunController::class, 'removePayslip'])
            ->name('runs.payslips.remove');
        Route::post('payroll-runs/{run}/finalise', [PayrollRunController::class, 'finalise'])->name('runs.finalise');
        Route::post('payroll-runs/{run}/mark-paid', [PayrollRunController::class, 'markPaid'])->name('runs.paid');

        // Leave administration
        Route::get('employees/{employee}/leave-balances', [LeaveBalanceController::class, 'forEmployee'])
            ->name('leave.balances');
        Route::post('leave/entitlements/grant', [LeaveBalanceController::class, 'grant'])->name('leave.grant');
        Route::post('leave/entitlements/adjust', [LeaveBalanceController::class, 'adjust'])->name('leave.adjust');
        
        // Attendance review
        Route::get('attendance', [AttendanceReviewController::class, 'pending'])->name('attendance.pending');
        Route::get('attendance/{sheet}', [AttendanceReviewController::class, 'show'])->name('attendance.show');
        Route::post('attendance/{sheet}/decide', [AttendanceReviewController::class, 'decide'])->name('attendance.decide');
    });
});
