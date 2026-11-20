<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        if (DB::table('users')->whereNull('department_id')->exists()) {
            throw new RuntimeException(
                'Assign a department to every user before running this migration.'
            );
        }

        DB::statement(
            'ALTER TABLE users ALTER COLUMN department_id SET NOT NULL'
        );
    }

    public function down(): void
    {
        DB::statement(
            'ALTER TABLE users ALTER COLUMN department_id DROP NOT NULL'
        );
    }
};