<?php

namespace App\Http\Controllers;

use App\Http\Resources\TicketEventResource;
use App\Models\RepairTicket;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Support\Facades\Gate;

class TicketEventController extends Controller
{
    public function index(
        Request $request,
        RepairTicket $ticket,
    ): AnonymousResourceCollection {
        $actor = $request->user();

        if (! ($actor instanceof User)) {
            abort(401, 'Unauthenticated.');
        }

        Gate::authorize('view', $ticket);

        $filters = $request->validate([
            'type' => ['sometimes', 'string', 'max:40'],
            'page' => ['sometimes', 'integer', 'min:1'],
            'per_page' => ['sometimes', 'integer', 'min:1', 'max:100'],
        ]);

        $query = $ticket->events()
            ->visibleTo($actor)
            ->with([
                'actor:id,name',

                'note' => function ($notes) use ($actor) {
                    $notes->visibleTo($actor)
                        ->with(['author', 'attachments']);
                },
            ]);

        if (isset($filters['type'])) {
            $query->where('type', $filters['type']);
        }

        $events = $query
            ->orderBy('created_at')
            ->orderBy('id')
            ->paginate((int) ($filters['per_page'] ?? 20))
            ->withQueryString();

        return TicketEventResource::collection($events);
    }
}