<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('information_requests', function (Blueprint $table) {
            $table->id();

            $table->foreignId('ticket_id')
                ->constrained('repair_tickets')
                ->restrictOnDelete();

            $table->foreignId('requested_by_id')
                ->constrained('users')
                ->restrictOnDelete();

            $table->foreignId('question_note_id')->unique();
            $table->timestampTz('requested_at');

            $table->foreignId('response_note_id')->nullable()->unique();

            $table->foreignId('responded_by_id')
                ->nullable()
                ->constrained('users')
                ->restrictOnDelete();

            $table->timestampTz('responded_at')->nullable();

            $table->unique(['id', 'ticket_id']);

            $table->foreign(['question_note_id', 'ticket_id'])
                ->references(['id', 'ticket_id'])
                ->on('ticket_notes')
                ->restrictOnDelete();

            $table->foreign(['response_note_id', 'ticket_id'])
                ->references(['id', 'ticket_id'])
                ->on('ticket_notes')
                ->restrictOnDelete();
        });

        DB::statement("
            CREATE UNIQUE INDEX information_requests_one_pending_per_ticket
            ON information_requests (ticket_id)
            WHERE responded_at IS NULL
        ");

        DB::statement("
            ALTER TABLE information_requests
            ADD CONSTRAINT information_requests_response_check
            CHECK (
                (
                    response_note_id IS NULL
                    AND responded_by_id IS NULL
                    AND responded_at IS NULL
                )
                OR
                (
                    response_note_id IS NOT NULL
                    AND responded_by_id IS NOT NULL
                    AND responded_at IS NOT NULL
                    AND responded_at >= requested_at
                )
            )
        ");

        Schema::table('ticket_events', function (Blueprint $table) {
            $table->foreignId('information_request_id')->nullable();

            $table->foreign(['information_request_id', 'ticket_id'])
                ->references(['id', 'ticket_id'])
                ->on('information_requests')
                ->restrictOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('ticket_events', function (Blueprint $table) {
            $table->dropForeign(['information_request_id', 'ticket_id']);
            $table->dropColumn('information_request_id');
        });

        Schema::dropIfExists('information_requests');
    }
};