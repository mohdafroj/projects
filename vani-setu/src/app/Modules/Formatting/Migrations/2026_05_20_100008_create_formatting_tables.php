<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('formatting_jobs', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('window_id')->nullable();
            $table->foreignId('sitting_id')->constrained('sittings')->cascadeOnDelete();
            $table->foreignId('formatter_user_id')->constrained('users');
            $table->enum('artifact_type', ['fv', 'hv', 'ev', 'synopsis']);
            $table->enum('status', ['draft', 'validated', 'crc_ready', 'dispatched'])->default('draft');
            $table->jsonb('metadata');
            $table->jsonb('policy_report')->nullable();
            $table->string('crc_source_hash', 64);
            $table->unsignedInteger('page_count')->default(0);
            $table->string('crc_path')->nullable();
            $table->foreignId('created_audit_log_id')->nullable()->constrained('audit_logs');
            $table->foreignId('validated_audit_log_id')->nullable()->constrained('audit_logs');
            $table->foreignId('crc_audit_log_id')->nullable()->constrained('audit_logs');
            $table->foreignId('dispatched_audit_log_id')->nullable()->constrained('audit_logs');
            $table->timestampsTz();
            $table->index(['artifact_type', 'status']);
            $table->index('window_id');
        });

        Schema::create('formatting_lines', function (Blueprint $table) {
            $table->id();
            $table->foreignId('job_id')->constrained('formatting_jobs')->cascadeOnDelete();
            $table->foreignId('block_id')->nullable()->constrained('blocks')->nullOnDelete();
            $table->unsignedInteger('sequence');
            $table->enum('kind', ['speaker', 'text', 'bifurcation', 'plot', 'oih', 'page_break']);
            $table->char('lang', 2)->nullable();
            $table->string('speaker_label')->nullable();
            $table->text('body')->nullable();
            $table->unsignedInteger('page_number');
            $table->jsonb('metadata')->nullable();
            $table->timestampsTz();
            $table->unique(['job_id', 'sequence']);
            $table->index(['job_id', 'page_number']);
        });

        Schema::create('formatting_transitions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('job_id')->constrained('formatting_jobs')->cascadeOnDelete();
            $table->foreignId('actor_id')->constrained('users');
            $table->string('action', 64);
            $table->string('from_status', 32)->nullable();
            $table->string('to_status', 32);
            $table->foreignId('audit_log_id')->constrained('audit_logs');
            $table->jsonb('payload')->nullable();
            $table->timestampsTz();
            $table->index(['job_id', 'action']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('formatting_transitions');
        Schema::dropIfExists('formatting_lines');
        Schema::dropIfExists('formatting_jobs');
    }
};
