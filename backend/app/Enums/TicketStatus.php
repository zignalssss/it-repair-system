<?php

namespace App\Enums;

enum TicketStatus: string
{
    case New = 'new';
    case InProgress = 'in_progress';
    case WaitingInfo = 'waiting_info';
    case Escalated = 'escalated';
    case Returned = 'returned';
    case WaitingEquipmentApproval = 'waiting_equipment_approval';
    case Resolved = 'resolved';
    case Cancelled = 'cancelled';
}