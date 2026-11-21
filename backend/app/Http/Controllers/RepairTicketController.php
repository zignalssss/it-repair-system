<?php

namespace App\Http\Controllers;

use App\Actions\RepairTickets\CreateRepairTicket;
use App\Http\Requests\Tickets\StoreRepairTicketRequest;
use App\Http\Resources\RepairTicketResource;
use App\Models\User;
use Illuminate\Http\JsonResponse;

class RepairTicketController extends Controller
{
    public function store(
        StoreRepairTicketRequest $request,
        CreateRepairTicket $createRepairTicket,
    ): JsonResponse {
        $requester = $request->user();

        if (! ($requester instanceof User)) {
            abort(401, 'Unauthenticated.');
        }

        $ticket = $createRepairTicket->handle(
            $requester,
            $request->validated(),
        );

        return (new RepairTicketResource($ticket))
            ->response()
            ->setStatusCode(201);
    }
}