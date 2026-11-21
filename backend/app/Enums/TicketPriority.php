<?php

namespace App\Enums;

enum TicketPriority: string
{
    case Normal = 'normal';
    case High = 'high';
    case Critical = 'critical';
}