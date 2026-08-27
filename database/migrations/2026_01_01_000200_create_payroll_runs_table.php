<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('payroll_runs', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->string('period', 7)->index();          // YYYY-MM
            $table->string('status', 20)->default('draft')->index();
            $table->date('payment_date');

            $table->foreignUuid('opened_by')->constrained('users');
            $table->timestamp('finalised_at')->nullable();
            $table->foreignUuid('finalised_by')->nullable()->constrained('users');
            $table->timestamp('paid_at')->nullable();

            $table->unsignedInteger('payslip_count')->default(0);
            $table->char('currency', 3)->default('MYR');
            $table->bigInteger('total_net_pay_minor')->default(0);
            $table->bigInteger('total_employer_cost_minor')->default(0);

            $table->text('notes')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('payroll_runs');
    }
};
