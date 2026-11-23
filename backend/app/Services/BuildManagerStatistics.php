<?php

namespace App\Services;

use App\Enums\TicketPriority;
use App\Enums\TicketStatus;
use App\Enums\UserRole;
use App\Models\RepairTicket;
use App\Models\TicketEvent;
use App\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\DB;

class BuildManagerStatistics
{
    public function handle(
        CarbonImmutable $start,
        CarbonImmutable $endExclusive,
    ): array {
        $topLevelTransaction = DB::transactionLevel() === 0;

        return DB::transaction(function () use ($start,$endExclusive,$topLevelTransaction) {
            if ($topLevelTransaction) {
                DB::statement(
                    'SET TRANSACTION ISOLATION LEVEL REPEATABLE READ, READ ONLY'
                );
            }

            $startValue = $start->toIso8601String();
            $endValue = $endExclusive->toIso8601String();

            $technicianRoles = [
                UserRole::JuniorTechnician->value,
                UserRole::SeniorTechnician->value,
            ];

            $createdQuery = RepairTicket::query()
                ->where('created_at', '>=', $startValue)
                ->where('created_at', '<', $endValue);

            $statusCounts = (clone $createdQuery)
                ->select('status')
                ->selectRaw('COUNT(*) AS total')
                ->groupBy('status')
                ->pluck('total', 'status');

            $priorityCounts = (clone $createdQuery)
                ->select('priority')
                ->selectRaw('COUNT(*) AS total')
                ->groupBy('priority')
                ->pluck('total', 'priority');

            $claims = TicketEvent::query()
                ->where('type', 'claimed')
                ->whereIn('actor_role', $technicianRoles)
                ->where('created_at', '>=', $startValue)
                ->where('created_at', '<', $endValue)
                ->select('actor_id')
                ->selectRaw('COUNT(*) AS claim_count')
                ->selectRaw(
                    'COUNT(DISTINCT ticket_id) AS claimed_ticket_count'
                )
                ->groupBy('actor_id')
                ->get()
                ->keyBy('actor_id');

            $resolvedCounts = RepairTicket::query()
                ->where('status', TicketStatus::Resolved->value)
                ->whereNotNull('resolved_by_id')
                ->where('resolved_at', '>=', $startValue)
                ->where('resolved_at', '<', $endValue)
                ->select('resolved_by_id')
                ->selectRaw('COUNT(*) AS total')
                ->groupBy('resolved_by_id')
                ->pluck('total', 'resolved_by_id');

            $openQuery = RepairTicket::query()
                ->whereNotIn('status', [
                    TicketStatus::Resolved->value,
                    TicketStatus::Cancelled->value,
                ]);

            $openTotal = (clone $openQuery)->count();

            $unassignedTotal = (clone $openQuery)
                ->whereNull('assignee_id')
                ->count();

            $assignedCounts = (clone $openQuery)
                ->whereNotNull('assignee_id')
                ->select('assignee_id')
                ->selectRaw('COUNT(*) AS total')
                ->groupBy('assignee_id')
                ->pluck('total', 'assignee_id');

            $historicalUserIds = $claims->keys()
                ->merge($resolvedCounts->keys())
                ->merge($assignedCounts->keys())
                ->unique()
                ->values();

            $technicians = User::query()
                ->where(function (Builder $users) use (
                    $technicianRoles,
                    $historicalUserIds,
                ) {
                    $users->whereIn('role', $technicianRoles)
                        ->orWhereIn('id', $historicalUserIds);
                })
                ->orderBy('name')
                ->orderBy('id')
                ->get(['id', 'name', 'role', 'is_active']);

            return [
                'generated_at' => CarbonImmutable::now('UTC')
                    ->toISOString(),

                'tickets_created_in_period' => [
                    'total' => (int) $statusCounts->sum(),

                    'by_current_status' => collect(TicketStatus::cases())
                        ->map(fn (TicketStatus $status) => [
                            'status' => $status->value,
                            'count' => (int) $statusCounts->get(
                                $status->value,
                                0,
                            ),
                        ])
                        ->all(),

                    'by_current_priority' => collect(TicketPriority::cases())
                        ->map(fn (TicketPriority $priority) => [
                            'priority' => $priority->value,
                            'count' => (int) $priorityCounts->get(
                                $priority->value,
                                0,
                            ),
                        ])
                        ->all(),
                ],

                'current_open_workload' => [
                    'total' => $openTotal,
                    'unassigned' => $unassignedTotal,
                    'assigned' => (int) $assignedCounts->sum(),
                ],

                'technicians' => $technicians
                    ->map(function (User $user) use (
                        $claims,
                        $resolvedCounts,
                        $assignedCounts,
                    ) {
                        $claim = $claims->get($user->id);

                        return [
                            'user_id' => $user->id,
                            'name' => $user->name,
                            'current_role' => $user->role->value,
                            'is_active' => $user->is_active,

                            'period_activity' => [
                                'claim_count' => (int) (
                                    $claim?->claim_count ?? 0
                                ),
                                'claimed_ticket_count' => (int) (
                                    $claim?->claimed_ticket_count ?? 0
                                ),
                                'resolved_ticket_count' => (int)
                                    $resolvedCounts->get($user->id, 0),
                            ],

                            'current_open_assigned' => (int)
                                $assignedCounts->get($user->id, 0),
                        ];
                    })
                    ->values()
                    ->all(),
            ];
        });
    }
}