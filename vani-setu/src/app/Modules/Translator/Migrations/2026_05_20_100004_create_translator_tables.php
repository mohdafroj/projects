<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('translator_assignments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('sitting_id')->constrained('sittings');
            $table->foreignId('slot_id')->nullable()->constrained('slots');
            $table->unsignedBigInteger('window_id')->nullable();
            $table->foreignId('translator_user_id')->constrained('users');
            $table->string('language_pair', 24);
            $table->string('status', 32)->default('open');
            $table->json('ai_translation_meta')->nullable();
            $table->timestamps();
            $table->index(['translator_user_id', 'language_pair', 'status'], 'translator_queue_idx');
        });

        Schema::create('translator_commits', function (Blueprint $table) {
            $table->id();
            $table->foreignId('assignment_id')->constrained('translator_assignments')->cascadeOnDelete();
            $table->foreignId('translator_user_id')->constrained('users');
            $table->unsignedInteger('block_count')->default(0);
            $table->unsignedInteger('edit_count')->default(0);
            $table->decimal('ai_acceptance_rate', 5, 2)->default(0);
            $table->timestamp('committed_at');
            $table->foreignId('committed_audit_log_id')->nullable()->constrained('audit_logs');
            $table->timestamps();
        });

        Schema::create('translator_edits', function (Blueprint $table) {
            $table->id();
            $table->foreignId('assignment_id')->constrained('translator_assignments')->cascadeOnDelete();
            $table->foreignId('block_id')->constrained('blocks');
            $table->string('kind', 24);
            $table->text('ai_suggestion')->nullable();
            $table->text('before')->nullable();
            $table->text('after')->nullable();
            $table->foreignId('audit_log_id')->nullable()->constrained('audit_logs');
            $table->timestamps();
            $table->index(['assignment_id', 'block_id']);
        });

        Schema::create('translator_glossary', function (Blueprint $table) {
            $table->id();
            $table->string('term_source');
            $table->string('term_target');
            $table->string('language_pair', 24);
            $table->string('domain', 32)->default('parliamentary');
            $table->text('notes')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users');
            $table->foreignId('approved_by')->nullable()->constrained('users');
            $table->timestamp('approved_at')->nullable();
            $table->timestamps();
            $table->unique(['term_source', 'language_pair'], 'translator_glossary_unique_term_pair');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('translator_glossary');
        Schema::dropIfExists('translator_edits');
        Schema::dropIfExists('translator_commits');
        Schema::dropIfExists('translator_assignments');
    }
};
