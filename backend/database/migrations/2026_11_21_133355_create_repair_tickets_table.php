<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('repair_tickets', function (Blueprint $table) {
            $table->id();

            $table->string('ticket_no', 30)
                ->storedAs("'RPR-' || id::text")
                ->unique();

            $table->foreignId('requester_id')
                ->constrained('users')
                ->restrictOnDelete();

            $table->foreignId('department_id')
                ->constrained('departments')
                ->restrictOnDelete();

            $table->string('title', 200);
            $table->text('description');
            $table->string('location', 255)->nullable();
            $table->string('asset_label', 100)->nullable();

            $table->enum('status', [
                'new',
                'in_progress',
                'waiting_info',
                'escalated',
                'returned',
                'waiting_equipment_approval',
                'resolved',
                'cancelled',
            ])->default('new');

            $table->enum('support_level', [
                'junior',
                'senior',
            ])->default('junior');

            $table->foreignId('assignee_id')
                ->nullable()
                ->constrained('users')
                ->restrictOnDelete();

            $table->enum('priority', [
                'normal',
                'high',
                'critical',
            ])->default('normal');

            $table->timestampTz('first_started_at')->nullable();
            $table->timestampTz('first_escalated_at')->nullable();
            $table->timestampTz('resolved_at')->nullable();

            $table->foreignId('resolved_by_id')
                ->nullable()
                ->constrained('users')
                ->restrictOnDelete();

            $table->timestampTz('cancelled_at')->nullable();

            $table->timestampsTz();

            $table->index(['department_id', 'created_at']);
            $table->index(['requester_id', 'created_at']);
            $table->index(['assignee_id', 'status']);
            $table->index(['support_level', 'status', 'created_at']);
            $table->index('resolved_by_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('repair_tickets');
    }
};