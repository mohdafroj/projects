<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('js_windows', function (Blueprint $table) {
            $table->id();
            $table->foreignId('sitting_id')->constrained('sittings')->cascadeOnDelete();
            $table->string('window_code', 16);
            $table->unsignedInteger('starts_at_offset_ms');
            $table->unsignedInteger('duration_ms')->default(3600000);
            $table->enum('status', ['open', 'under_review', 'sent_to_sg', 'sg_returned', 'approved', 'published_handoff'])->default('open');
            $table->timestampsTz();
            $table->unique(['sitting_id', 'window_code']);
        });

        Schema::create('js_decisions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('window_id')->constrained('js_windows')->cascadeOnDelete();
            $table->enum('kind', ['accept_se', 'decline_se', 'speaker_override', 'text_edit', 'expunge_confirm', 'expunge_override']);
            $table->foreignId('actor_id')->constrained('users');
            $table->jsonb('payload');
            $table->foreignId('audit_log_id')->constrained('audit_logs');
            $table->timestampsTz();
        });

        Schema::create('js_sg_handoffs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('window_id')->constrained('js_windows')->cascadeOnDelete();
            $table->timestampTz('sent_at');
            $table->foreignId('sent_audit_log_id')->constrained('audit_logs');
            $table->timestampTz('returned_at')->nullable();
            $table->foreignId('returned_audit_log_id')->nullable()->constrained('audit_logs');
            $table->string('dsc_serial', 64)->nullable();
            $table->foreignId('sg_user_id')->nullable()->constrained('users');
            $table->unsignedInteger('confirmed_expunges')->default(0);
            $table->unsignedInteger('manual_expunges')->default(0);
            $table->timestampsTz();
        });

        Schema::create('suggested_edits', function (Blueprint $table) {
            $table->id();
            $table->foreignId('window_id')->constrained('js_windows')->cascadeOnDelete();
            $table->enum('source', ['member', 'minister', 'ai']);
            $table->string('source_name');
            $table->foreignId('source_member_id')->nullable()->constrained('members');
            $table->foreignId('block_id')->constrained('blocks')->cascadeOnDelete();
            $table->text('before');
            $table->text('after');
            $table->text('reason');
            $table->enum('state', ['pending', 'accepted', 'declined'])->default('pending');
            $table->timestampsTz();
        });

        Schema::create('expunge_candidates', function (Blueprint $table) {
            $table->id();
            $table->foreignId('window_id')->constrained('js_windows')->cascadeOnDelete();
            $table->foreignId('block_id')->constrained('blocks')->cascadeOnDelete();
            $table->string('word');
            $table->text('grounds');
            $table->string('master_db_ref', 80);
            $table->enum('state', ['pending', 'confirmed', 'overridden'])->default('pending');
            $table->timestampsTz();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('expunge_candidates');
        Schema::dropIfExists('suggested_edits');
        Schema::dropIfExists('js_sg_handoffs');
        Schema::dropIfExists('js_decisions');
        Schema::dropIfExists('js_windows');
    }
};
