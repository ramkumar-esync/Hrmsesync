<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('employees', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->foreignUuid('user_id')->nullable()->unique()
                ->constrained('users')->nullOnDelete();

            $table->string('employee_number', 20)->unique();
            $table->string('name', 150);
            $table->string('work_email')->unique();
            $table->date('joined_on');
            $table->date('left_on')->nullable();
            $table->string('status', 20)->default('probation')->index();
            $table->string('job_title', 120);
            $table->string('department', 120)->nullable()->index();

            // Money is stored as integer minor units. Never as a float.
            $table->char('currency', 3)->default('MYR');
            $table->unsignedBigInteger('basic_salary_minor')->default(0);
            $table->unsignedBigInteger('fixed_allowance_minor')->nullable();
            $table->string('pay_frequency', 10)->default('monthly');

            // Statutory profile
            $table->date('date_of_birth');
            $table->boolean('is_citizen')->default(true);
            $table->boolean('epf_applicable')->default(true);
            $table->boolean('socso_applicable')->default(true);
            $table->boolean('eis_applicable')->default(true);
            $table->string('epf_number', 30)->nullable();
            $table->string('socso_number', 30)->nullable();
            $table->string('tax_reference_number', 30)->nullable();
            $table->string('national_id_number', 30)->nullable();
            $table->unsignedTinyInteger('tax_dependants')->default(0);
            $table->boolean('is_married')->default(false);

            $table->string('bank_name', 100)->nullable();
            $table->string('bank_account_number', 30)->nullable();

            $table->foreignUuid('reports_to')->nullable()
                ->constrained('employees')->nullOnDelete();

            $table->timestamps();

            $table->index(['status', 'joined_on']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('employees');
    }
};
