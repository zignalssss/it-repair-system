<?php

namespace App\Actions\RepairTickets;

use App\Enums\SupportLevel;
use App\Enums\TicketPriority;
use App\Enums\TicketStatus;
use App\Models\RepairTicket;
use App\Models\User;
use App\Services\NoteAttachmentStorage;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Throwable;

class CreateRepairTicket
{
    public function __construct(
        private readonly NoteAttachmentStorage $attachments,
    ) {}

    /** @param list<UploadedFile> $files */
    public function handle(
        User $requester,
        array $data,
        array $files = [],
    ): RepairTicket {
        $storedPaths = [];

        try {
            return DB::transaction(function () use (
                $requester,
                $data,
                $files,
                &$storedPaths,
            ) {
                $ticket = new RepairTicket($data);

                $ticket->requester()->associate($requester);
                $ticket->department_id = $requester->department_id;
                $ticket->status = TicketStatus::New;
                $ticket->support_level = SupportLevel::Junior;
                $ticket->priority = TicketPriority::Normal;

                $ticket->save();
                $ticket->refresh();

                $initialNote = null;

                if ($files !== []) {
                    $initialNote = $ticket->notes()->create([
                        'author_id' => $requester->id,
                        'author_role' => $requester->role,
                        'kind' => 'initial',
                        'audience' => 'requester',
                        'body' => null,
                    ]);

                    $this->attachments->store(
                        $initialNote,
                        $requester,
                        $files,
                        $storedPaths,
                    );
                }

                $ticket->events()->create([
                    'actor_id' => $requester->id,
                    'actor_role' => $requester->role,
                    'type' => 'created',
                    'audience' => 'requester',
                    'from_status' => null,
                    'to_status' => $ticket->status,
                    'from_level' => null,
                    'to_level' => $ticket->support_level,
                    'note_id' => $initialNote?->id,
                    'metadata' => [
                        'ticket_no' => $ticket->ticket_no,
                    ],
                ]);

                return $ticket->load([
                    'requester',
                    'department',
                    'assignee',
                ]);
            });
        } catch (Throwable $error) {
            $this->attachments->cleanup($storedPaths);

            throw $error;
        }
    }
}