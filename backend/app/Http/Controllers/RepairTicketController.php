<?php

namespace App\Http\Controllers;

use App\Models\User;
use App\Http\Requests\Tickets\IndexRepairTicketRequest;
use App\Models\RepairTicket;
use App\Actions\RepairTickets\CreateRepairTicket;
use App\Http\Requests\Tickets\StoreRepairTicketRequest;
use App\Http\Resources\RepairTicketResource;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Gate;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;


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

    public function index(
        IndexRepairTicketRequest $request,
    ): AnonymousResourceCollection 
{
        $user = $request->user();

        if (! ($user instanceof User)) {
            abort(401, 'Unauthenticated.');
        }

        $filters = $request->validated();

        $query = RepairTicket::query()
            ->visibleTo($user)
            ->with([
                'requester',
                'department',
                'assignee',
            ]);

        if (isset($filters['status'])) {
            $query->where('status', $filters['status']);
        }

        $tickets = $query
            ->orderByDesc('created_at')
            ->orderByDesc('id')
            ->paginate((int) ($filters['per_page'] ?? 15))
            ->withQueryString();

        return RepairTicketResource::collection($tickets);
    }

    public function show(RepairTicket $ticket): RepairTicketResource
    {
        Gate::authorize('view', $ticket);

        $ticket->load([
            'requester',
            'department',
            'assignee',
        ]);

        return new RepairTicketResource($ticket);
}
}