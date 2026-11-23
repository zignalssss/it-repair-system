<?php

namespace App\Http\Controllers;

use App\Http\Requests\Reports\ReportSummaryRequest;
use App\Services\BuildManagerStatistics;
use App\Models\RepairTicket;
use App\Services\GenerateRepairTicketPdf;
use Carbon\CarbonImmutable;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Http\JsonResponse;

class ReportController extends Controller
{
    public function summary(
        ReportSummaryRequest $request,
        BuildManagerStatistics $statistics,
    ): JsonResponse {
        $data = $request->validated();
        $timezone = 'Asia/Bangkok';

        $start = CarbonImmutable::parse($data['from'],$timezone)
            ->startOfDay()
            ->setTimezone('UTC');

        $endExclusive = CarbonImmutable::parse($data['to'],$timezone)
            ->startOfDay()
            ->addDay()
            ->setTimezone('UTC');

        $result = $statistics->handle($start, $endExclusive);

        return response()->json([
            'data' => [
                'period' => [
                    'from' => $data['from'],
                    'to' => $data['to'],
                    'timezone' => $timezone,
                ],
                ...$result,
            ],
        ]);
    }
    public function ticketPdf(
    Request $request,
    RepairTicket $ticket,
    GenerateRepairTicketPdf $pdf,
    ): Response {
        $content = $pdf->handle($ticket, $request);

        return response($content, 200, [
            'Content-Type' => 'application/pdf',
            'Content-Disposition' =>
                'attachment; filename="'.$ticket->ticket_no.'.pdf"',
            'Cache-Control' => 'private, no-store',
            'X-Content-Type-Options' => 'nosniff',
        ]);
    }
}