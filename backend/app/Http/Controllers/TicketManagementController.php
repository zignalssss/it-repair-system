<?php

namespace App\Http\Controllers;

use App\Actions\RepairTickets\AssignRepairTicket;
use App\Actions\RepairTickets\UpdateTicketPriority;
use App\Enums\TicketPriority;
use App\Enums\UserRole;
use App\Http\Requests\Tickets\AssignRepairTicketRequest;
use App\Http\Requests\Tickets\UpdateTicketPriorityRequest;
use App\Http\Resources\RepairTicketResource;
use App\Http\Resources\UserResource;
use App\Models\RepairTicket;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Support\Facades\Gate;

class TicketManagementController extends Controller
{
    public function technicians(
        Request $request,
    ): AnonymousResourceCollection {
        Gate::authorize('viewTechnicians', RepairTicket::class);

        $request->validate([
            'page' => ['sometimes', 'integer', 'min:1'],
        ]);

        $technicians = User::query()
            ->where('is_active', true)
            ->whereIn('role', [
                UserRole::JuniorTechnician->value,
                UserRole::SeniorTechnician->value,
            ])
            ->with('department')
            ->orderBy('name')
            ->orderBy('id')
            ->paginate(50);

        return UserResource::collection($technicians);
    }

    public function assign(
        AssignRepairTicketRequest $request,
        RepairTicket $ticket,
        AssignRepairTicket $action,
    ): JsonResponse {
        $actor = $this->authenticatedUser($request);
        $data = $request->validated();

        $updatedTicket = $action->handle(
            $actor,
            $ticket->id,
            (int) $data['assignee_id'],
            $data['reason'] ?? null,
        );

        return (new RepairTicketResource($updatedTicket))
            ->response()
            ->setStatusCode(200);
    }

    public function updatePriority(
        UpdateTicketPriorityRequest $request,
        RepairTicket $ticket,
        UpdateTicketPriority $action,
    ): JsonResponse {
        $actor = $this->authenticatedUser($request);
        $data = $request->validated();

        $updatedTicket = $action->handle(
            $actor,
            $ticket->id,
            TicketPriority::from($data['priority']),
        );

        return (new RepairTicketResource($updatedTicket))
            ->response()
            ->setStatusCode(200);
    }

    private function authenticatedUser(Request $request): User
    {
        $user = $request->user();

        if (! ($user instanceof User)) {
            abort(401, 'Unauthenticated.');
        }

        return $user;
    }
}