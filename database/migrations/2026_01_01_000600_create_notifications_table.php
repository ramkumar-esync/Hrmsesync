<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('app_notifications', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->foreignUuid('user_id')->constrained('users')->cascadeOnDelete();

            // A short machine type ('leave.approved', 'attendance.returned', …)
            // plus human title/body and an optional in-app link to act on it.
            $table->string('type', 40);
            $table->string('title', 160);
            $table->text('body')->nullable();
            $table->string('action_url', 200)->nullable();

            // Null until the user has seen it. Read state is what stops a
            // notification showing again after the first login that surfaces it.
            $table->timestamp('read_at')->nullable();

            $table->timestamps();

            $table->index(['user_id', 'read_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('app_notifications');
    }
};
