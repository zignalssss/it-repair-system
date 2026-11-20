<?php

namespace Database\Seeders;

use App\Enums\UserRole;
use App\Models\Department;
use App\Models\User;
use Illuminate\Database\Seeder;

class UserSeeder extends Seeder
{
    public function run(): void
    {
        $accounts = [
            [
                'name' => 'Finance User',
                'email' => 'test@example.com',
                'department' => 'FIN',
                'role' => UserRole::User,
            ],
            [
                'name' => 'Finance Colleague',
                'email' => 'finance2@example.com',
                'department' => 'FIN',
                'role' => UserRole::User,
            ],
            [
                'name' => 'Sales User',
                'email' => 'sales@example.com',
                'department' => 'SALES',
                'role' => UserRole::User,
            ],
            [
                'name' => 'Junior Technician',
                'email' => 'junior@example.com',
                'department' => 'IT',
                'role' => UserRole::JuniorTechnician,
            ],
            [
                'name' => 'Senior Technician',
                'email' => 'senior@example.com',
                'department' => 'IT',
                'role' => UserRole::SeniorTechnician,
            ],
            [
                'name' => 'IT Manager',
                'email' => 'manager@example.com',
                'department' => 'IT',
                'role' => UserRole::ITManager,
            ],
        ];

        foreach ($accounts as $account) {
            $department = Department::where(
                'code',
                $account['department'],
            )->firstOrFail();

            $user = User::firstOrNew([
                'email' => $account['email'],
            ]);

            $user->name = $account['name'];
            $user->department()->associate($department);
            $user->role = $account['role'];
            $user->is_active = true;

            if (! $user->exists) {
                $user->password = 'DevOnly123!';
            }

            $user->save();
        }
    }
}