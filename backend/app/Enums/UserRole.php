<?php

namespace App\Enums;

enum UserRole: string
{
    case User = 'user';
    case JuniorTechnician = 'junior_technician';
    case SeniorTechnician = 'senior_technician';
    case ITManager = 'it_manager';
}