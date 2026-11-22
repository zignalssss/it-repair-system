<?php

namespace App\Http\Controllers;


use App\Actions\RepairTickets\CancelRepairTicket;
use App\Actions\RepairTickets\ClaimRepairTicket;
use App\Actions\RepairTickets\RequestTicketInformation;
use App\Actions\RepairTickets\RespondTicketInformation;
use App\Actions\RepairTickets\CreateRepairTicket;
use App\Actions\RepairTickets\AddWorkLog;

use App\Models\InformationRequest;
use App\Models\RepairTicket;
use App\Models\User;

use App\Http\Requests\Tickets\InformationMessageRequest;
use App\Http\Requests\Tickets\CancelRepairTicketRequest;
use App\Http\Requests\Tickets\IndexRepairTicketRequest;
use App\Http\Requests\Tickets\StoreRepairTicketRequest;
use App\Http\Requests\Tickets\StoreWorkLogRequest;

use App\Http\Resources\RepairTicketResource;
use App\Http\Resources\TicketNoteResource;

use Illuminate\Http\Request;
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
            'pendingInformationRequest.questionNote.author',
        ]);

        return new RepairTicketResource($ticket);
    }

    public function cancel(
        CancelRepairTicketRequest $request,
        RepairTicket $ticket,
        CancelRepairTicket $cancelRepairTicket,
    ): RepairTicketResource {
        $actor = $request->user();

        if (! ($actor instanceof User)) {
            abort(401, 'Unauthenticated.');
        }

        $data = $request->validated();

        $updatedTicket = $cancelRepairTicket->handle(
            $actor,
            $ticket->id,
            $data['reason'],
        );

        return new RepairTicketResource($updatedTicket);
    }

    public function claim(
        Request $request,
        RepairTicket $ticket,
        ClaimRepairTicket $claimRepairTicket,
    ): RepairTicketResource {
        $actor = $request->user();

        if (! ($actor instanceof User)) {
            abort(401, 'Unauthenticated.');
        }

        $updatedTicket = $claimRepairTicket->handle(
            $actor,
            $ticket->id,
        );

        return new RepairTicketResource($updatedTicket);
    }
    public function addWorkLog(
        StoreWorkLogRequest $request,
        RepairTicket $ticket,
        AddWorkLog $addWorkLog,
    ): JsonResponse {
        $actor = $request->user();

        if (! ($actor instanceof User)) {
            abort(401, 'Unauthenticated.');
        }

        $data = $request->validated();

        $note = $addWorkLog->handle(
            $actor,
            $ticket->id,
            $data['body'],
        );

        return (new TicketNoteResource($note))
            ->response()
            ->setStatusCode(201);
    }

    public function requestInformation(
        InformationMessageRequest $request,
        RepairTicket $ticket,
        RequestTicketInformation $action,
    ): JsonResponse {
        $actor = $request->user();

        if (! ($actor instanceof User)) {
            abort(401, 'Unauthenticated.');
        }

        $data = $request->validated();

        $updatedTicket = $action->handle(
            $actor,
            $ticket->id,
            $data['body'],
        );

        return (new RepairTicketResource($updatedTicket))
            ->response()
            ->setStatusCode(201);
    }

    public function respondInformation(
        InformationMessageRequest $request,
        RepairTicket $ticket,
        InformationRequest $informationRequest,
        RespondTicketInformation $action,
    ): RepairTicketResource {
        $actor = $request->user();

        if (! ($actor instanceof User)) {
            abort(401, 'Unauthenticated.');
        }

        $data = $request->validated();

        $updatedTicket = $action->handle(
            $actor,
            $ticket->id,
            $informationRequest->id,
            $data['body'],
        );

        return new RepairTicketResource($updatedTicket);
    }

}