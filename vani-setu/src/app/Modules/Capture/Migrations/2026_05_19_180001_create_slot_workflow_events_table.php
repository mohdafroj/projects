<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('slot_workflow_events', function (Blueprint $table) {
            $table->id();
            $table->foreignId('slot_assignment_id')->constrained()->cascadeOnDelete();
            $table->string('from_stage');
            $table->string('to_stage');
            $table->enum('action', ['commit', 'forward', 'return']);
            $table->foreignId('actor_id')->constrained('users');
            $table->string('actor_role');
            $table->text('reason')->nullable();
            $table->foreignId('audit_log_id')->constrained('audit_logs');
            $table->timestampTz('created_at');

            $table->index(['slot_assignment_id', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('slot_workflow_events');
    }
};
