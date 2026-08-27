<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('payslips', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->foreignUuid('payroll_run_id')->constrained('payroll_runs')->cascadeOnDelete();
            $table->foreignUuid('employee_id')->constrained('employees');
            $table->string('period', 7)->index();
            $table->char('currency', 3)->default('MYR');

            // Frozen copy of the employee's details as they were when issued.
            $table->json('employee_snapshot');

            // Lines are read as a whole document and never queried individually.
            $table->json('earnings');
            $table->json('deductions');
            $table->json('employer_contributions');

            // Denormalised totals for reporting without rehydrating every payslip.
            $table->bigInteger('gross_pay_minor')->default(0);
            $table->bigInteger('total_deductions_minor')->default(0);
            $table->bigInteger('net_pay_minor')->default(0);
            $table->bigInteger('employer_cost_minor')->default(0);

            $table->string('status', 20)->default('draft')->index();
            $table->timestamp('issued_at')->nullable();
            $table->string('document_path')->nullable();
            $table->foreignUuid('supersedes_payslip_id')->nullable()
                ->constrained('payslips')->nullOnDelete();
            $table->text('remarks')->nullable();

            $table->timestamps();

            // One payslip per employee per run.
            $table->unique(['payroll_run_id', 'employee_id']);
            $table->index(['employee_id', 'period']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('payslips');
    }
};
