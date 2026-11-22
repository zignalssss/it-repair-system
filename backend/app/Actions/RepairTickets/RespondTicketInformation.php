<?php

namespace App\Actions\RepairTickets;

use App\Enums\TicketStatus;
use App\Models\RepairTicket;
use App\Models\User;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use RuntimeException;
use Throwable;

class RespondTicketInformation
{
    /** @param list<UploadedFile> $files */
    public function handle(
        User $actor,
        int $ticketId,
        int $informationRequestId,
        ?string $body,
        array $files = [],
    ): RepairTicket {
        $storedPaths = [];

        try {
            return DB::transaction(function () use (
                $actor,
                $ticketId,
                $informationRequestId,
                $body,
                $files,
                &$storedPaths,
            ) {
                $ticket = RepairTicket::query()
                    ->lockForUpdate()
                    ->findOrFail($ticketId);

                Gate::forUser($actor)->authorize('view', $ticket);
                Gate::forUser($actor)->authorize('respondInformation', $ticket);

                $informationRequest = $ticket->informationRequests()
                    ->lockForUpdate()
                    ->findOrFail($informationRequestId);

                if (
                    $ticket->status !== TicketStatus::WaitingInfo
                    || $informationRequest->responded_at !== null
                ) {
                    abort(409, 'คำขอนี้ไม่ได้อยู่ระหว่างรอคำตอบ');
                }

                if ($ticket->assignee_id === null) {
                    abort(409, 'ใบงานนี้ไม่มีช่างผู้รับผิดชอบ');
                }

                $note = $ticket->notes()->create([
                    'author_id' => $actor->id,
                    'author_role' => $actor->role,
                    'kind' => 'info_response',
                    'audience' => 'requester',
                    'body' => $body,
                ]);

                foreach ($files as $file) {
                    $directory = "tickets/{$ticket->id}/notes/{$note->id}";
                    $filename = Str::uuid().'.'.$file->extension();
                    $path = $directory.'/'.$filename;

                    $storedPaths[] = $path;

                    $writtenPath = Storage::disk('private')->putFileAs(
                        $directory,
                        $file,
                        $filename,
                    );

                    if ($writtenPath === false) {
                        throw new RuntimeException('Cannot store attachment.');
                    }

                    $originalName = basename(
                        str_replace('\\', '/', $file->getClientOriginalName()),
                    );

                    $note->attachments()->create([
                        'uploaded_by_id' => $actor->id,
                        'disk' => 'private',
                        'storage_path' => $path,
                        'original_name' => $originalName,
                        'mime_type' => $file->getMimeType()
                            ?? 'application/octet-stream',
                        'size_bytes' => $file->getSize(),
                    ]);
                }

                $informationRequest->responseNote()->associate($note);
                $informationRequest->responded_by_id = $actor->id;
                $informationRequest->responded_at = now();
                $informationRequest->save();

                $fromStatus = $ticket->status;

                $ticket->status = TicketStatus::InProgress;
                $ticket->save();

                $ticket->events()->create([
                    'actor_id' => $actor->id,
                    'actor_role' => $actor->role,
                    'type' => 'info_responded',
                    'audience' => 'requester',
                    'from_status' => $fromStatus,
                    'to_status' => $ticket->status,
                    'from_level' => $ticket->support_level,
                    'to_level' => $ticket->support_level,
                    'from_assignee_id' => $ticket->assignee_id,
                    'to_assignee_id' => $ticket->assignee_id,
                    'note_id' => $note->id,
                    'information_request_id' => $informationRequest->id,
                ]);

                return $ticket->load([
                    'requester',
                    'department',
                    'assignee',
                    'pendingInformationRequest.questionNote.author',
                ]);
            });
        } catch (Throwable $error) {
            if ($storedPaths !== []) {
                try {
                    $deleted = Storage::disk('private')->delete($storedPaths);

                    if (! $deleted) {
                        report(new RuntimeException(
                            'Cannot clean up failed attachment upload.',
                        ));
                    }
                } catch (Throwable $cleanupError) {
                    report($cleanupError);
                }
            }

            throw $error;
        }
    }
}