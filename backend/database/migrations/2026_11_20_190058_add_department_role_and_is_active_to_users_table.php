<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->foreignId('department_id')
                ->nullable()
                ->constrained('departments')
                ->restrictOnDelete();

            $table->string('role', 30)->default('user');
            $table->boolean('is_active')->default(true);

            $table->index('department_id');
        });

        DB::statement("
            ALTER TABLE users
            ADD CONSTRAINT users_role_check
            CHECK (
                role IN (
                    'user',
                    'junior_technician',
                    'senior_technician',
                    'it_manager'
                )
            )
        ");
    }

    public function down(): void
    {
        DB::statement(
            'ALTER TABLE users DROP CONSTRAINT users_role_check'
        );

        Schema::table('users', function (Blueprint $table) {
            $table->dropForeign(['department_id']);
            $table->dropIndex(['department_id']);

            $table->dropColumn([
                'department_id',
                'role',
                'is_active',
            ]);
        });
    }
};