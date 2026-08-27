<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('attendance_sheets', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->foreignUuid('employee_id')->constrained('employees')->cascadeOnDelete();

            // The month this sheet covers, as YYYY-MM. One sheet per employee
            // per month is enforced by the unique index below.
            $table->string('period', 7)->index();

            $table->string('status', 20)->default('draft')->index();

            // The reported rows, stored together as JSON: the employee edits the
            // whole month as one set, and they are never queried row by row here
            // (leave reconciliation reads the Leave context, not this column).
            $table->json('entries');
            $table->unsignedInteger('total_minutes')->default(0);

            $table->timestamp('submitted_at')->nullable();
            $table->timestamp('decided_at')->nullable();
            $table->foreignUuid('decided_by')->nullable()->constrained('employees');
            $table->text('decision_note')->nullable();

            $table->timestamps();

            $table->unique(['employee_id', 'period']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('attendance_sheets');
    }
};
