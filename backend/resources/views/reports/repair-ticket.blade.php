<!DOCTYPE html>
<html lang="th">
<head>
    <meta charset="UTF-8">

    <style>
        body {
            font-family: garuda;
            font-size: 11pt;
            color: #222;
        }

        h1 {
            font-size: 19pt;
            margin-bottom: 4px;
        }

        h2 {
            font-size: 14pt;
            margin-top: 18px;
            margin-bottom: 8px;
            page-break-after: avoid;
        }

        h3 {
            font-size: 12pt;
            margin-bottom: 5px;
            page-break-after: avoid;
        }

        p {
            margin: 4px 0;
        }

        table {
            width: 100%;
            border-collapse: collapse;
            margin: 8px 0;
        }

        th, td {
            border: 1px solid #ccc;
            padding: 6px;
            vertical-align: top;
        }

        th {
            background-color: #f1f1f1;
            text-align: left;
        }

        .muted {
            color: #666;
            font-size: 9pt;
        }

        .right {
            text-align: right;
        }

        .text {
            line-height: 1.6;
        }

        hr {
            border: 0;
            border-top: 1px solid #ddd;
            margin: 12px 0;
        }
    </style>
</head>
<body>
@php
    $statusLabels = [
        'new' => 'รอรับเรื่อง',
        'in_progress' => 'กำลังดำเนินการ',
        'waiting_info' => 'รอข้อมูลเพิ่มเติม',
        'escalated' => 'ส่งต่อช่างอาวุโส',
        'returned' => 'ส่งกลับช่างระดับต้น',
        'waiting_equipment_approval' => 'รออนุมัติอุปกรณ์',
        'resolved' => 'แก้ไขเสร็จสิ้น',
        'cancelled' => 'ยกเลิก',
    ];

    $priorityLabels = [
        'normal' => 'ปกติ',
        'high' => 'สำคัญ',
        'critical' => 'วิกฤต',
    ];

    $roleLabels = [
        'user' => 'ผู้ใช้งาน',
        'junior_technician' => 'ช่างระดับต้น',
        'senior_technician' => 'ช่างอาวุโส',
        'it_manager' => 'ผู้จัดการ IT',
    ];

    $levelLabels = [
        'junior' => 'ช่างระดับต้น',
        'senior' => 'ช่างอาวุโส',
    ];

    $equipmentStatusLabels = [
        'pending' => 'รอพิจารณา',
        'approved' => 'อนุมัติ',
        'rejected' => 'ปฏิเสธ',
    ];

    $metadataLabels = [
        'ticket_no' => 'เลขที่ใบงาน',
        'reason' => 'เหตุผล',
        'from_priority' => 'ความเร่งด่วนเดิม',
        'to_priority' => 'ความเร่งด่วนใหม่',
        'parts_count' => 'จำนวนรายการอะไหล่ใช้จริง',
    ];

    $formatDate = static fn ($value) => $value
        ? \Carbon\CarbonImmutable::parse($value)
            ->setTimezone('Asia/Bangkok')
            ->format('d/m/Y H:i')
        : '-';
@endphp

<h1>รายงานใบแจ้งซ่อม</h1>

<p><strong>เลขที่:</strong> {{ $ticket->ticket_no }}</p>
<p class="muted">
    ออกรายงานเมื่อ {{ $formatDate($generatedAt) }} น.
    เวลาไทย — สำหรับผู้จัดการ IT
</p>

<h2>1. รายละเอียดใบแจ้งซ่อม</h2>

<table>
    <tr>
        <th width="25%">ผู้แจ้ง</th>
        <td>{{ $ticket->requester?->name ?? '-' }}</td>
    </tr>
    <tr>
        <th>แผนกตอนแจ้ง</th>
        <td>{{ $ticket->department?->name ?? '-' }}</td>
    </tr>
    <tr>
        <th>สถานะ</th>
        <td>{{ $statusLabels[$ticket->status->value] }}</td>
    </tr>
    <tr>
        <th>ความเร่งด่วน</th>
        <td>{{ $priorityLabels[$ticket->priority->value] }}</td>
    </tr>
    <tr>
        <th>ผู้รับผิดชอบ</th>
        <td>{{ $ticket->assignee?->name ?? '-' }}</td>
    </tr>
    <tr>
        <th>สถานที่ / รหัสเครื่อง</th>
        <td>
            {{ $ticket->location ?? '-' }}
            /
            {{ $ticket->asset_label ?? '-' }}
        </td>
    </tr>
    <tr>
        <th>แจ้งเมื่อ</th>
        <td>{{ $formatDate($ticket->created_at) }}</td>
    </tr>
    <tr>
        <th>เริ่มดำเนินการครั้งแรก</th>
        <td>{{ $formatDate($ticket->first_started_at) }}</td>
    </tr>
    <tr>
        <th>ปิดงาน / ผู้ปิดงาน</th>
        <td>
            {{ $formatDate($ticket->resolved_at) }}
            /
            {{ $ticket->resolvedBy?->name ?? '-' }}
        </td>
    </tr>
    <tr>
        <th>ยกเลิกเมื่อ</th>
        <td>{{ $formatDate($ticket->cancelled_at) }}</td>
    </tr>
</table>

<h3>{{ $ticket->title }}</h3>

<div class="text">
    {!! nl2br(e($ticket->description)) !!}
</div>

<h2>2. ประวัติการดำเนินการ</h2>

@foreach ($ticket->events as $event)
    <h3>{{ $loop->iteration }}. {{ $event->label() }}</h3>

    <p class="muted">
        {{ $formatDate($event->created_at) }} น.
        — {{ $event->actor?->name ?? '-' }}
        ({{ $roleLabels[$event->actor_role->value] }})
    </p>

    <p>
        สถานะ:
        {{ $statusLabels[$event->from_status?->value] ?? '-' }}
        →
        {{ $statusLabels[$event->to_status?->value] ?? '-' }}
    </p>

    <p>
        ระดับ:
        {{ $levelLabels[$event->from_level?->value] ?? '-' }}
        →
        {{ $levelLabels[$event->to_level?->value] ?? '-' }}
    </p>

    @if ($event->from_assignee_id !== $event->to_assignee_id)
        <p>
            ผู้รับผิดชอบ:
            {{ $userNames->get($event->from_assignee_id, '-') }}
            →
            {{ $userNames->get($event->to_assignee_id, '-') }}
        </p>
    @endif

    @if ($event->note)
        <div class="text">
            {!! nl2br(e($event->note->body ?? '')) !!}
        </div>

        @if ($event->note->attachments->isNotEmpty())
            <p><strong>ไฟล์แนบ:</strong></p>
            <ul>
                @foreach ($event->note->attachments as $attachment)
                    <li>
                        {{ $attachment->original_name }}
                        — {{ $attachment->mime_type }}
                        — {{ number_format($attachment->size_bytes / 1024, 1) }} KB
                    </li>
                @endforeach
            </ul>
        @endif
    @endif

    @foreach (($event->metadata ?? []) as $key => $value)
        @php
            $displayValue = is_scalar($value)
                ? (string) $value
                : ($value === null
                    ? '-'
                    : json_encode($value, JSON_UNESCAPED_UNICODE));

            if (in_array($key, ['from_priority', 'to_priority'], true)) {
                $displayValue = $priorityLabels[$displayValue] ?? $displayValue;
            }
        @endphp

        <p>
            <strong>{{ $metadataLabels[$key] ?? $key }}:</strong>
            {!! nl2br(e($displayValue)) !!}
        </p>
    @endforeach

    @if ($event->equipment_request_id)
        <p>อ้างอิงคำขออุปกรณ์ #{{ $event->equipment_request_id }}</p>
    @endif

    @if ($event->information_request_id)
        <p>อ้างอิงคำขอข้อมูล #{{ $event->information_request_id }}</p>
    @endif

    <hr>
@endforeach

<h2>3. คำขออุปกรณ์และผลพิจารณา</h2>

@forelse ($ticket->equipmentRequests as $equipmentRequest)
    <h3>
        คำขอ #{{ $equipmentRequest->id }}
        — {{ $equipmentStatusLabels[$equipmentRequest->status->value] }}
    </h3>

    <p>
        ผู้ขอ:
        {{ $userNames->get($equipmentRequest->requested_by_id, '-') }}
        — {{ $formatDate($equipmentRequest->requested_at) }}
    </p>

    <div class="text">
        {!! nl2br(e($equipmentRequest->reason)) !!}
    </div>

    <table>
        <thead>
            <tr>
                <th>รหัสรายการ</th>
                <th>อุปกรณ์</th>
                <th>จำนวนที่ขอ</th>
                <th>ราคาประเมิน/หน่วย (บาท)</th>
            </tr>
        </thead>
        <tbody>
            @foreach ($equipmentRequest->items as $item)
                <tr>
                    <td>#{{ $item->id }}</td>
                    <td>{{ $item->name }}</td>
                    <td>{{ $item->quantity }}</td>
                    <td class="right">{{ $item->estimated_unit_price }}</td>
                </tr>
            @endforeach

            <tr>
                <td colspan="3"><strong>รวมราคาประเมิน</strong></td>
                <td class="right">
                    {{ $equipmentRequest->estimated_total }}
                </td>
            </tr>
        </tbody>
    </table>

    <p>
        ผู้พิจารณา:
        {{ $userNames->get($equipmentRequest->decided_by_id, '-') }}
        — {{ $formatDate($equipmentRequest->decided_at) }}
    </p>

    <p><strong>เหตุผลประกอบผลพิจารณา:</strong></p>

    <div class="text">
        {!! nl2br(e($equipmentRequest->decision_reason ?? '-')) !!}
    </div>

    <hr>
@empty
    <p>ไม่มีคำขออุปกรณ์</p>
@endforelse

<h2>4. อะไหล่ที่ใช้จริง</h2>

<table>
    <thead>
        <tr>
            <th>อะไหล่</th>
            <th>จำนวน</th>
            <th>แหล่งที่มา</th>
            <th>ผู้บันทึก / เวลา</th>
        </tr>
    </thead>
    <tbody>
        @forelse ($ticket->partsUsed as $part)
            <tr>
                <td>{{ $part->name }}</td>
                <td>{{ $part->quantity }}</td>
                <td>
                    @if ($part->equipment_request_item_id)
                        รายการอนุมัติ #{{ $part->equipment_request_item_id }}
                    @else
                        อะไหล่สำรอง
                    @endif
                </td>
                <td>
                    {{ $part->recordedBy?->name ?? '-' }}
                    <br>
                    {{ $formatDate($part->recorded_at) }}
                </td>
            </tr>
        @empty
            <tr>
                <td colspan="4">ไม่มีรายการอะไหล่ใช้จริงที่บันทึกไว้</td>
            </tr>
        @endforelse
    </tbody>
</table>

</body>
</html>