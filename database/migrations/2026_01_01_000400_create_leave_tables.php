<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('leave_types', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->string('code', 20)->unique();
            $table->string('name', 100);
            $table->boolean('is_paid')->default(true);
            $table->string('accrual_policy', 20)->default('annual_grant');
            $table->decimal('default_entitlement_days', 5, 2)->default(0);
            $table->decimal('carry_forward_cap', 5, 2)->default(0);
            $table->unsignedTinyInteger('carry_forward_expiry_months')->default(3);
            $table->boolean('allow_half_day')->default(true);
            $table->boolean('requires_attachment')->default(false);
            $table->unsignedSmallInteger('max_consecutive_days')->nullable();
            $table->unsignedSmallInteger('min_notice_days')->default(0);
            $table->boolean('allow_backdating')->default(false);
            $table->boolean('is_active')->default(true)->index();
            $table->timestamps();
        });

        Schema::create('leave_entitlements', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->foreignUuid('employee_id')->constrained('employees')->cascadeOnDelete();
            $table->foreignUuid('leave_type_id')->constrained('leave_types');
            $table->unsignedSmallInteger('year');

            $table->decimal('entitled_days', 6, 2)->default(0);
            $table->decimal('carried_forward_days', 6, 2)->default(0);
            $table->decimal('adjustment_days', 6, 2)->default(0);
            $table->decimal('taken_days', 6, 2)->default(0);
            $table->decimal('pending_days', 6, 2)->default(0);
            $table->date('carry_forward_expires_on')->nullable();

            $table->timestamps();

            // One balance row per employee, type and year — the row that gets
            // locked when leave is applied for.
            $table->unique(['employee_id', 'leave_type_id', 'year']);
        });

        Schema::create('leave_applications', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->foreignUuid('employee_id')->constrained('employees')->cascadeOnDelete();
            $table->foreignUuid('leave_type_id')->constrained('leave_types');
            $table->unsignedSmallInteger('entitlement_year');

            $table->date('start_date')->index();
            $table->date('end_date')->index();
            $table->decimal('working_days', 5, 2);

            $table->text('reason');
            $table->string('attachment_path')->nullable();
            $table->string('contact_while_away', 120)->nullable();

            $table->string('status', 20)->default('pending')->index();
            $table->timestamp('applied_at');
            $table->foreignUuid('decided_by')->nullable()->constrained('employees')->nullOnDelete();
            $table->timestamp('decided_at')->nullable();
            $table->text('decision_note')->nullable();

            $table->timestamps();

            $table->index(['employee_id', 'status']);
        });

        // One row per calendar day. This is what makes it possible to split a
        // leave spell across two payroll periods without guesswork.
        Schema::create('leave_days', function (Blueprint $table): void {
            $table->id();
            $table->foreignUuid('leave_application_id')
                ->constrained('leave_applications')->cascadeOnDelete();
            $table->date('date')->index();
            $table->string('portion', 12)->default('full');
            $table->boolean('is_deductible')->default(true);
            $table->string('non_working_reason', 100)->nullable();

            $table->unique(['leave_application_id', 'date']);
        });

        Schema::create('public_holidays', function (Blueprint $table): void {
            $table->id();
            $table->date('date')->index();
            $table->string('name', 120);
            // Null means it applies nationwide.
            $table->string('region', 60)->nullable()->index();
            $table->timestamps();

            $table->unique(['date', 'region']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('public_holidays');
        Schema::dropIfExists('leave_days');
        Schema::dropIfExists('leave_applications');
        Schema::dropIfExists('leave_entitlements');
        Schema::dropIfExists('leave_types');
    }
};
