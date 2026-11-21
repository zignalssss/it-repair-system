<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('ticket_events', function (Blueprint $table) {
            $table->id();

            $table->foreignId('ticket_id')
                ->constrained('repair_tickets')
                ->restrictOnDelete();

            $table->foreignId('actor_id')
                ->constrained('users')
                ->restrictOnDelete();

            $table->string('actor_role', 30);
            $table->string('type', 40);

            $table->enum('audience', [
                'requester',
                'junior',
                'senior',
                'manager',
            ]);

            $table->string('from_status', 40)->nullable();
            $table->string('to_status', 40)->nullable();

            $table->string('from_level', 20)->nullable();
            $table->string('to_level', 20)->nullable();

            $table->foreignId('from_assignee_id')
                ->nullable()
                ->constrained('users')
                ->restrictOnDelete();

            $table->foreignId('to_assignee_id')
                ->nullable()
                ->constrained('users')
                ->restrictOnDelete();

            $table->jsonb('metadata')->default('{}');
            $table->timestampTz('created_at')->useCurrent();

            $table->index(['ticket_id', 'created_at', 'id']);
            $table->index(['actor_id', 'type', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('ticket_events');
    }
};