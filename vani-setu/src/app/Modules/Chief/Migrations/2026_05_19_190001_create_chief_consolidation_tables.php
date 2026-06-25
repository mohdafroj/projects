<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('chief_consolidations', function (Blueprint $table) {
            $table->id();
            $table->foreignId('sitting_id')->constrained()->cascadeOnDelete();
            $table->char('window_code', 1);
            $table->integer('starts_at_offset_ms');
            $table->integer('duration_ms')->default(1800000);
            $table->enum('status', ['open', 'en_committed', 'hi_committed', 'dual_committed', 'forwarded_to_js'])->default('open');
            $table->timestamps();

            $table->unique(['sitting_id', 'window_code']);
            $table->index(['status', 'window_code']);
        });

        Schema::create('chief_consolidation_commits', function (Blueprint $table) {
            $table->id();
            $table->foreignId('consolidation_id')->constrained('chief_consolidations')->cascadeOnDelete();
            $table->foreignId('chief_user_id')->constrained('users');
            $table->char('lang_side', 2);
            $table->integer('block_count')->default(0);
            $table->integer('edit_count')->default(0);
            $table->integer('custom_member_count')->default(0);
            $table->timestampTz('committed_at');
            $table->foreignId('committed_audit_log_id')->constrained('audit_logs');
            $table->timestamps();

            $table->unique(['consolidation_id', 'lang_side']);
        });

        Schema::create('chief_edits', function (Blueprint $table) {
            $table->id();
            $table->foreignId('consolidation_id')->constrained('chief_consolidations')->cascadeOnDelete();
            $table->foreignId('block_id')->constrained('blocks')->cascadeOnDelete();
            $table->foreignId('chief_user_id')->constrained('users');
            $table->enum('kind', ['text', 'speaker']);
            $table->text('before')->nullable();
            $table->text('after')->nullable();
            $table->text('before_hi')->nullable();
            $table->text('after_hi')->nullable();
            $table->foreignId('audit_log_id')->constrained('audit_logs');
            $table->timestamps();

            $table->index(['consolidation_id', 'block_id']);
        });

        Schema::create('chief_speaker_overrides', function (Blueprint $table) {
            $table->id();
            $table->foreignId('consolidation_id')->constrained('chief_consolidations')->cascadeOnDelete();
            $table->foreignId('block_id')->constrained('blocks')->cascadeOnDelete();
            $table->foreignId('reporter_member_id')->nullable()->constrained('members');
            $table->foreignId('chief_member_id')->nullable()->constrained('members');
            $table->foreignId('chief_custom_member_id')->nullable()->constrained('member_customs');
            $table->foreignId('chief_user_id')->constrained('users');
            $table->timestamps();

            $table->unique(['consolidation_id', 'block_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('chief_speaker_overrides');
        Schema::dropIfExists('chief_edits');
        Schema::dropIfExists('chief_consolidation_commits');
        Schema::dropIfExists('chief_consolidations');
    }
};
