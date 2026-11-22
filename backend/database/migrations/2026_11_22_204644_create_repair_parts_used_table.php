<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('repair_parts_used', function (Blueprint $table) {
            $table->id();

            $table->foreignId('ticket_id')
                ->constrained('repair_tickets')
                ->restrictOnDelete();

            $table->foreignId('equipment_request_item_id')
                ->nullable()
                ->constrained('equipment_request_items')
                ->restrictOnDelete();

            $table->string('name', 200);
            $table->integer('quantity');

            $table->foreignId('recorded_by_id')
                ->constrained('users')
                ->restrictOnDelete();

            $table->timestampTz('recorded_at')->useCurrent();

            $table->index(['ticket_id', 'recorded_at']);
            $table->index('equipment_request_item_id');
        });

        DB::statement("
            ALTER TABLE repair_parts_used
            ADD CONSTRAINT repair_parts_used_quantity_check
            CHECK (quantity BETWEEN 1 AND 1000)
        ");
    }

    public function down(): void
    {
        Schema::dropIfExists('repair_parts_used');
    }
};