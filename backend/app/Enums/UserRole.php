<?php

namespace App\Enums;

enum UserRole: string
{
    case User = 'user';
    case JuniorTechnician = 'junior_technician';
    case SeniorTechnician = 'senior_technician';
    case ITManager = 'it_manager';

    public function readableAudiences(): array
    {
        return match ($this) {
            self::User => ['requester'],

            self::JuniorTechnician => [
                'requester',
                'junior',
            ],

            self::SeniorTechnician => [
                'requester',
                'junior',
                'senior',
            ],

            self::ITManager => [
                'requester',
                'junior',
                'senior',
                'manager',
            ],
        };
    }
}