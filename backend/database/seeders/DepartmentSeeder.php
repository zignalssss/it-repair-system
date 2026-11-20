<?php

namespace Database\Seeders;

use App\Models\Department;
use Illuminate\Database\Seeder;

class DepartmentSeeder extends Seeder
{
    public function run(): void
    {
        $departments = [
            [
                'code' => 'FIN',
                'name' => 'ฝ่ายการเงิน',
            ],
            [
                'code' => 'SALES',
                'name' => 'ฝ่ายขาย',
            ],
            [
                'code' => 'HR',
                'name' => 'ฝ่ายทรัพยากรบุคคล',
            ],
            [
                'code' => 'IT',
                'name' => 'ฝ่ายไอที',
            ],
        ];

        foreach ($departments as $department) {
            Department::updateOrCreate(
                [
                    'code' => $department['code'],
                ],
                [
                    'name' => $department['name'],
                    'is_active' => true,
                ],
            );
        }
    }
}