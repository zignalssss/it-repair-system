<?php

namespace App\Enums;

enum EquipmentRequestStatus: string
{
    case Pending = 'pending';
    case Approved = 'approved';
    case Rejected = 'rejected';
}