<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('attachments', function (Blueprint $table) {
            $table->id();

            $table->foreignId('note_id')
                ->constrained('ticket_notes')
                ->restrictOnDelete();

            $table->foreignId('uploaded_by_id')
                ->constrained('users')
                ->restrictOnDelete();

            $table->string('disk', 30)->default('private');
            $table->string('storage_path', 500)->unique();
            $table->string('original_name', 255);
            $table->string('mime_type', 100);
            $table->bigInteger('size_bytes');
            $table->timestampTz('created_at')->useCurrent();

            $table->index('note_id');
        });

        DB::statement("
            ALTER TABLE attachments
            ADD CONSTRAINT attachments_size_check
            CHECK (size_bytes > 0 AND size_bytes <= 20971520)
        ");
    }

    public function down(): void
    {
        Schema::dropIfExists('attachments');
    }
};