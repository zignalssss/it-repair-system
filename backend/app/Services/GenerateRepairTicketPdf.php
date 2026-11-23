<?php

namespace App\Services;

use App\Models\EquipmentRequest;
use App\Models\RepairTicket;
use App\Models\TicketEvent;
use App\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Gate;
use Mpdf\Mpdf;

class GenerateRepairTicketPdf
{
    public function handle(
        RepairTicket $ticket,
        Request $request,
    ): string {
        $actor = $request->user();

        if (! ($actor instanceof User)) {
            abort(401, 'Unauthenticated.');
        }

        Gate::forUser($actor)->authorize(
            'viewReports',
            RepairTicket::class,
        );

        Gate::forUser($actor)->authorize('view', $ticket);

        $data = $this->reportData($ticket);

        $tempDir = storage_path('app/mpdf');
        File::ensureDirectoryExists($tempDir);

        $pdf = new Mpdf([
            'mode' => 'utf-8',
            'format' => 'A4',
            'default_font' => 'garuda',
            'default_font_size' => 11,
            'margin_left' => 14,
            'margin_right' => 14,
            'margin_top' => 16,
            'margin_bottom' => 18,
            'autoScriptToLang' => true,
            'autoLangToFont' => true,
            'tempDir' => $tempDir,
        ]);

        $pdf->SetTitle('ใบแจ้งซ่อม '.$ticket->ticket_no);

        $pdf->SetHTMLFooter(
            '<div style="text-align:right;font-size:9pt">'
            .'หน้า {PAGENO} / {nbpg}'
            .'</div>'
        );

        $html = view('reports.repair-ticket', $data)->render();

        $pdf->WriteHTML($html);

        return $pdf->OutputBinaryData();
    }

    private function reportData(RepairTicket $ticket): array
    {
        $topLevelTransaction = DB::transactionLevel() === 0;

        return DB::transaction(function () use (
            $ticket,
            $topLevelTransaction,
        ) {
            if ($topLevelTransaction) {
                DB::statement(
                    'SET TRANSACTION ISOLATION LEVEL REPEATABLE READ, READ ONLY'
                );
            }

            $reportTicket = RepairTicket::query()
                ->with([
                    'requester',
                    'department',
                    'assignee',
                    'resolvedBy',

                    'events' => function ($events) {
                        $events->orderBy('created_at')->orderBy('id');
                    },

                    'events.actor',
                    'events.note.attachments',

                    'equipmentRequests' => function ($requests) {
                        $requests->orderBy('requested_at')
                            ->orderBy('id')
                            ->withSum(
                                'items as estimated_total',
                                DB::raw('quantity * estimated_unit_price'),
                            );
                    },

                    'equipmentRequests.items',

                    'partsUsed' => function ($parts) {
                        $parts->orderBy('recorded_at')->orderBy('id');
                    },

                    'partsUsed.recordedBy',
                ])
                ->findOrFail($ticket->id);

            $userIds = $reportTicket->events
                ->flatMap(fn (TicketEvent $event) => [
                    $event->from_assignee_id,
                    $event->to_assignee_id,
                ])
                ->merge(
                    $reportTicket->equipmentRequests->flatMap(
                        fn (EquipmentRequest $equipmentRequest) => [
                            $equipmentRequest->requested_by_id,
                            $equipmentRequest->decided_by_id,
                        ],
                    ),
                )
                ->filter()
                ->unique()
                ->values();

            return [
                'ticket' => $reportTicket,

                'userNames' => User::query()
                    ->whereIn('id', $userIds)
                    ->pluck('name', 'id'),

                'generatedAt' => CarbonImmutable::now('UTC'),
            ];
        });
    }
}