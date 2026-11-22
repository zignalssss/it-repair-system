<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('equipment_requests', function (Blueprint $table) {
            $table->id();

            $table->foreignId('ticket_id')
                ->constrained('repair_tickets')
                ->restrictOnDelete();

            $table->foreignId('requested_by_id')
                ->constrained('users')
                ->restrictOnDelete();

            $table->text('reason');

            $table->enum('status', [
                'pending',
                'approved',
                'rejected',
            ])->default('pending');

            $table->timestampTz('requested_at');

            $table->foreignId('decided_by_id')
                ->nullable()
                ->constrained('users')
                ->restrictOnDelete();

            $table->text('decision_reason')->nullable();
            $table->timestampTz('decided_at')->nullable();

            $table->unique(['id', 'ticket_id']);
            $table->index(['ticket_id', 'requested_at', 'id']);
        });

        Schema::create('equipment_request_items', function (Blueprint $table) {
            $table->id();

            $table->foreignId('equipment_request_id')
                ->constrained('equipment_requests')
                ->restrictOnDelete();

            $table->string('name', 200);
            $table->integer('quantity');
            $table->decimal('estimated_unit_price', 12, 2);

            $table->index('equipment_request_id');
        });

        DB::statement("
            CREATE UNIQUE INDEX equipment_requests_one_pending_per_ticket
            ON equipment_requests (ticket_id)
            WHERE status = 'pending'
        ");

        DB::statement("
            ALTER TABLE equipment_request_items
            ADD CONSTRAINT equipment_request_items_values_check
            CHECK (
                quantity BETWEEN 1 AND 1000
                AND estimated_unit_price >= 0
            )
        ");

        DB::statement("
            ALTER TABLE equipment_requests
            ADD CONSTRAINT equipment_requests_decision_check
            CHECK (
                (
                    status = 'pending'
                    AND decided_by_id IS NULL
                    AND decided_at IS NULL
                    AND decision_reason IS NULL
                )
                OR
                (
                    status IN ('approved', 'rejected')
                    AND decided_by_id IS NOT NULL
                    AND decided_at IS NOT NULL
                    AND decided_at >= requested_at
                    AND (
                        status <> 'rejected'
                        OR (
                            decision_reason IS NOT NULL
                            AND length(trim(decision_reason)) > 0
                        )
                    )
                )
            )
        ");

        Schema::table('ticket_events', function (Blueprint $table) {
            $table->foreignId('equipment_request_id')->nullable();

            $table->foreign(['equipment_request_id', 'ticket_id'])
                ->references(['id', 'ticket_id'])
                ->on('equipment_requests')
                ->restrictOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('ticket_events', function (Blueprint $table) {
            $table->dropForeign(['equipment_request_id', 'ticket_id']);
            $table->dropColumn('equipment_request_id');
        });

        Schema::dropIfExists('equipment_request_items');
        Schema::dropIfExists('equipment_requests');
    }
};