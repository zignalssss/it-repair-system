<?php

namespace App\Actions\RepairTickets;

use App\Enums\SupportLevel;
use App\Enums\TicketPriority;
use App\Enums\TicketStatus;
use App\Models\RepairTicket;
use App\Models\User;
use Illuminate\Support\Facades\DB;

class CreateRepairTicket
{
    public function handle(User $requester, array $data): RepairTicket
    {
        return DB::transaction(function () use ($requester, $data) {
            $ticket = new RepairTicket($data);

            $ticket->requester()->associate($requester);
            $ticket->department_id = $requester->department_id;
            $ticket->status = TicketStatus::New;
            $ticket->support_level = SupportLevel::Junior;
            $ticket->priority = TicketPriority::Normal;

            $ticket->save();
            $ticket->refresh();

            $ticket->events()->create([
                'actor_id' => $requester->id,
                'actor_role' => $requester->role,
                'type' => 'created',
                'audience' => 'requester',
                'from_status' => null,
                'to_status' => $ticket->status,
                'from_level' => null,
                'to_level' => $ticket->support_level,
                'metadata' => [
                    'ticket_no' => $ticket->ticket_no,
                ],
            ]);

            return $ticket->load([
                'requester',
                'department',
            ]);
        });
    }
}