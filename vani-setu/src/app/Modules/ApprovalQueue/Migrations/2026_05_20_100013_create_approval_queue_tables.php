<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('approval_queue_actions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('item_key', 160);
            $table->string('module', 48);
            $table->string('action', 32);
            $table->text('note')->nullable();
            $table->timestampTz('snoozed_until')->nullable();
            $table->foreignId('audit_log_id')->nullable()->constrained('audit_logs')->nullOnDelete();
            $table->timestampsTz();

            $table->unique(['user_id', 'item_key']);
            $table->index(['user_id', 'action', 'snoozed_until']);
            $table->index(['module', 'item_key']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('approval_queue_actions');
    }
};
