<?php

namespace App\Services;

use App\Models\TicketNote;
use App\Models\User;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use RuntimeException;
use Throwable;

class NoteAttachmentStorage
{
    /**
     * @param list<UploadedFile> $files
     * @param list<string> $storedPaths
     */
    public function store(
        TicketNote $note,
        User $actor,
        array $files,
        array &$storedPaths,
    ): void {

        foreach ($files as $file) {
            $directory = "tickets/{$note->ticket_id}/notes/{$note->id}";
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
    }

    /** @param list<string> $paths */
    public function cleanup(array $paths): void
    {
        if ($paths === []) {
            return;
        }

        try {
            $deleted = Storage::disk('private')->delete($paths);

            if (! $deleted) {
                report(new RuntimeException(
                    'Cannot clean up failed attachment upload.',
                ));
            }
        } catch (Throwable $error) {
            report($error);
        }
    }
}