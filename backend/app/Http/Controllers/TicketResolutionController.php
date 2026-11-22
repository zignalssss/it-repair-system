<?php

namespace App\Http\Controllers;

use App\Actions\RepairTickets\ResolveRepairTicket;
use App\Http\Requests\Tickets\ResolveRepairTicketRequest;
use App\Http\Resources\RepairTicketResource;
use App\Models\RepairTicket;
use App\Models\User;
use Illuminate\Http\JsonResponse;

class TicketResolutionController extends Controller
{
    public function resolve(
        ResolveRepairTicketRequest $request,
        RepairTicket $ticket,
        ResolveRepairTicket $action,
    ): JsonResponse {
        $actor = $request->user();

        if (! ($actor instanceof User)) {
            abort(401, 'Unauthenticated.');
        }

        $data = $request->validated();

        $updatedTicket = $action->handle(
            $actor,
            $ticket->id,
            $data['summary'],
            $data['parts'] ?? [],
        );

        return (new RepairTicketResource($updatedTicket))
            ->response()
            ->setStatusCode(200);
    }
}