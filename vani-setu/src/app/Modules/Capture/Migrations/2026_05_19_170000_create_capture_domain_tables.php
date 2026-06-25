<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('sittings', function (Blueprint $table) {
            $table->id();
            $table->integer('session_no');
            $table->integer('sitting_no');
            $table->date('sitting_date');
            $table->enum('status', ['planned', 'live', 'closed']);
            $table->timestamp('started_at')->nullable();
            $table->timestamp('ended_at')->nullable();
            $table->timestamps();
            $table->unique(['session_no', 'sitting_no']);
        });

        Schema::create('members', function (Blueprint $table) {
            $table->id();
            $table->string('roster_id')->unique();
            $table->enum('category', ['chair', 'minister', 'member'])->index();
            $table->string('name_en');
            $table->string('name_hi');
            $table->string('party')->nullable();
            $table->string('state_jur')->nullable();
            $table->string('role_title')->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestamps();
            $table->index('roster_id');
        });

        Schema::create('slots', function (Blueprint $table) {
            $table->id();
            $table->foreignId('sitting_id')->constrained()->cascadeOnDelete();
            $table->string('code');
            $table->integer('start_offset_ms');
            $table->integer('duration_ms')->default(300000);
            $table->string('topic');
            $table->enum('status', ['open', 'in_progress', 'committed_partial', 'committed_full'])->default('open');
            $table->timestamps();
            $table->unique(['sitting_id', 'code']);
        });

        Schema::create('member_customs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('slot_id')->constrained()->cascadeOnDelete();
            $table->string('name_en');
            $table->string('name_hi');
            $table->string('role_title')->nullable();
            $table->string('state_jur')->nullable();
            $table->foreignId('created_by_user_id')->constrained('users');
            $table->timestamps();
        });

        Schema::create('blocks', function (Blueprint $table) {
            $table->id();
            $table->foreignId('slot_id')->constrained()->cascadeOnDelete();
            $table->integer('sequence');
            $table->integer('start_ms');
            $table->integer('end_ms');
            $table->char('original_lang', 2);
            $table->char('chief_lang', 2);
            $table->enum('ai_action', ['native', 'translated', 'recovered']);
            $table->text('ai_text');
            $table->text('text');
            $table->text('translated_text')->nullable();
            $table->foreignId('member_id')->nullable()->constrained('members');
            $table->foreignId('custom_member_id')->nullable()->constrained('member_customs');
            $table->integer('version')->default(1);
            $table->integer('reporter_edit_count')->default(0);
            $table->timestamps();
            $table->index(['slot_id', 'sequence']);
        });

        if (DB::getDriverName() === 'pgsql') {
            DB::statement(
                'ALTER TABLE blocks ADD CONSTRAINT blocks_one_speaker CHECK ((member_id IS NULL) OR (custom_member_id IS NULL))'
            );

            DB::statement(
                "ALTER TABLE blocks ADD CONSTRAINT blocks_original_lang CHECK (original_lang IN ('en', 'hi', 'ta', 'ur', 'bn', 'mr'))"
            );

            DB::statement(
                "ALTER TABLE blocks ADD CONSTRAINT blocks_chief_lang CHECK (chief_lang IN ('en', 'hi'))"
            );
        }

        Schema::create('slot_assignments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('slot_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->constrained('users');
            $table->char('lang_role', 2);
            $table->enum('status', ['open', 'in_progress', 'committed'])->default('open');
            $table->timestamp('committed_at')->nullable();
            $table->foreignId('committed_audit_log_id')->nullable()->constrained('audit_logs');
            $table->timestamps();
            $table->unique(['slot_id', 'lang_role']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('slot_assignments');
        Schema::dropIfExists('blocks');
        Schema::dropIfExists('member_customs');
        Schema::dropIfExists('slots');
        Schema::dropIfExists('members');
        Schema::dropIfExists('sittings');
    }
};
