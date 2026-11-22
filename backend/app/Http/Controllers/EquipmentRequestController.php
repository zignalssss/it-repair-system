<?php

namespace App\Http\Controllers;

use App\Actions\Equipment\EquipmentRequestWorkflow;
use App\Enums\EquipmentRequestStatus;
use App\Http\Requests\Tickets\DecideEquipmentRequest;
use App\Http\Requests\Tickets\StoreEquipmentRequest;
use App\Http\Resources\EquipmentRequestResource;
use App\Models\EquipmentRequest;
use App\Models\RepairTicket;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;

class EquipmentRequestController extends Controller
{
    public function index(
        Request $request,
        RepairTicket $ticket,
    ): AnonymousResourceCollection {
        Gate::authorize('view', $ticket);
        Gate::authorize('viewEquipmentRequests', $ticket);

        $filters = $request->validate([
            'status' => [
                'sometimes',
                Rule::enum(EquipmentRequestStatus::class),
            ],
            'page' => ['sometimes', 'integer', 'min:1'],
        ]);

        $query = $ticket->equipmentRequests()
            ->with(['items', 'ticket'])
            ->withSum(
                'items as estimated_total',
                DB::raw('quantity * estimated_unit_price'),
            );

        if (isset($filters['status'])) {
            $query->where('status', $filters['status']);
        }

        return EquipmentRequestResource::collection(
            $query->orderByDesc('id')->paginate(20)->withQueryString(),
        );
    }

    public function store(
        StoreEquipmentRequest $request,
        RepairTicket $ticket,
        EquipmentRequestWorkflow $workflow,
    ): JsonResponse {
        $data = $request->validated();

        $equipmentRequest = $workflow->request(
            $this->authenticatedUser($request),
            $ticket->id,
            $data['reason'],
            $data['items'],
        );

        return (new EquipmentRequestResource($equipmentRequest))
            ->response()
            ->setStatusCode(201);
    }

    public function decide(
        DecideEquipmentRequest $request,
        RepairTicket $ticket,
        EquipmentRequest $equipmentRequest,
        EquipmentRequestWorkflow $workflow,
    ): JsonResponse {
        $data = $request->validated();

        $updated = $workflow->decide(
            $this->authenticatedUser($request),
            $ticket->id,
            $equipmentRequest->id,
            EquipmentRequestStatus::from($data['status']),
            $data['reason'] ?? null,
        );

        return (new EquipmentRequestResource($updated))
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