<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('synopsis_documents', function (Blueprint $table) {
            $table->id();
            $table->foreignId('consolidation_id')->constrained('chief_consolidations')->cascadeOnDelete();
            $table->foreignId('sitting_id')->constrained()->cascadeOnDelete();
            $table->foreignId('writer_user_id')->nullable()->constrained('users');
            $table->char('chunk_code', 1);
            $table->integer('starts_at_offset_ms');
            $table->integer('duration_ms')->default(1800000);
            $table->enum('source_mode', ['ai', 'scratch'])->default('scratch');
            $table->enum('status', ['empty', 'draft', 'submitted', 'final'])->default('empty');
            $table->string('title')->nullable();
            $table->longText('body')->nullable();
            $table->json('attributions')->nullable();
            $table->boolean('ai_first_draft')->default(false);
            $table->integer('version')->default(1);
            $table->timestampTz('submitted_at')->nullable();
            $table->timestampTz('finalised_at')->nullable();
            $table->foreignId('finalised_by_user_id')->nullable()->constrained('users');
            $table->foreignId('last_audit_log_id')->nullable()->constrained('audit_logs');
            $table->timestamps();

            $table->unique(['consolidation_id']);
            $table->index(['sitting_id', 'chunk_code', 'status']);
        });

        Schema::create('synopsis_document_edits', function (Blueprint $table) {
            $table->id();
            $table->foreignId('synopsis_document_id')->constrained('synopsis_documents')->cascadeOnDelete();
            $table->foreignId('writer_user_id')->constrained('users');
            $table->enum('kind', ['generate', 'author', 'save', 'submit', 'finalise']);
            $table->integer('from_version')->nullable();
            $table->integer('to_version')->nullable();
            $table->text('before_excerpt')->nullable();
            $table->text('after_excerpt')->nullable();
            $table->foreignId('audit_log_id')->constrained('audit_logs');
            $table->timestamps();

            $table->index(['synopsis_document_id', 'kind']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('synopsis_document_edits');
        Schema::dropIfExists('synopsis_documents');
    }
};
