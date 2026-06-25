<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('s2s_sessions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('sitting_id')->nullable()->constrained('sittings');
            $table->foreignId('started_by_user_id')->nullable()->constrained('users');
            $table->string('source_lang', 16);
            $table->string('target_lang', 16);
            $table->string('status', 32)->default('queued');
            $table->json('engine_meta')->nullable();
            $table->timestamp('started_at')->nullable();
            $table->timestamp('finished_at')->nullable();
            $table->timestamps();
        });

        Schema::create('s2s_segments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('session_id')->constrained('s2s_sessions')->cascadeOnDelete();
            $table->unsignedInteger('sequence_no');
            $table->unsignedInteger('start_ms')->default(0);
            $table->unsignedInteger('end_ms')->default(0);
            $table->text('source_text')->nullable();
            $table->text('target_text')->nullable();
            $table->string('source_audio_path')->nullable();
            $table->string('target_audio_path')->nullable();
            $table->foreignId('audit_log_id')->nullable()->constrained('audit_logs');
            $table->timestamps();
            $table->unique(['session_id', 'sequence_no']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('s2s_segments');
        Schema::dropIfExists('s2s_sessions');
    }
};
