<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('ticket_notes', function (Blueprint $table) {
            $table->id();

            $table->foreignId('ticket_id')
                ->constrained('repair_tickets')
                ->restrictOnDelete();

            $table->foreignId('author_id')
                ->constrained('users')
                ->restrictOnDelete();

            $table->enum('author_role', [
                'user',
                'junior_technician',
                'senior_technician',
                'it_manager',
            ]);

            $table->enum('kind', [
                'initial',
                'work_log',
                'info_question',
                'info_response',
                'handoff',
                'decision_summary',
                'resolution',
                'cancellation',
            ]);

            $table->enum('audience', [
                'requester',
                'junior',
                'senior',
                'manager',
            ]);

            $table->text('body')->nullable();
            $table->timestampTz('created_at')->useCurrent();

            $table->unique(['id', 'ticket_id']);
            $table->index(['ticket_id', 'created_at', 'id']);
        });

        Schema::table('ticket_events', function (Blueprint $table) {
            $table->foreignId('note_id')->nullable();

            $table->foreign(['note_id', 'ticket_id'])
                ->references(['id', 'ticket_id'])
                ->on('ticket_notes')
                ->restrictOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('ticket_events', function (Blueprint $table) {
            $table->dropForeign(['note_id', 'ticket_id']);
            $table->dropColumn('note_id');
        });

        Schema::dropIfExists('ticket_notes');
    }
};