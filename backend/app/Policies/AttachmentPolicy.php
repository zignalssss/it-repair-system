<?php

namespace App\Policies;

use App\Models\Attachment;
use App\Models\TicketNote;
use App\Models\User;
use Illuminate\Auth\Access\Response;

class AttachmentPolicy
{
    public function view(User $user, Attachment $attachment): Response
    {
        $visible = TicketNote::query()
            ->visibleTo($user)
            ->whereKey($attachment->note_id)
            ->exists();

        return $visible
            ? Response::allow()
            : Response::denyAsNotFound();
    }
}