<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('s2s_sessions', function (Blueprint $table) {
            $table->string('title')->nullable()->after('started_by_user_id');
            $table->string('mode', 24)->default('live')->after('title');
            $table->string('input_source', 32)->default('microphone')->after('mode');
            $table->string('listener_scope', 32)->default('hybrid')->after('input_source');
            $table->json('available_target_langs')->nullable()->after('target_lang');
            $table->json('audio_input_meta')->nullable()->after('available_target_langs');
            $table->json('archive_meta')->nullable()->after('audio_input_meta');
            $table->json('fallback_meta')->nullable()->after('archive_meta');
            $table->text('announcement_text')->nullable()->after('fallback_meta');
        });

        Schema::table('s2s_segments', function (Blueprint $table) {
            $table->string('source_language', 16)->nullable()->after('end_ms');
            $table->string('status', 24)->default('queued')->after('target_audio_path');
            $table->json('translated_segments')->nullable()->after('status');
            $table->json('engine_meta')->nullable()->after('translated_segments');
        });

        Schema::create('s2s_outputs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('session_id')->constrained('s2s_sessions')->cascadeOnDelete();
            $table->foreignId('segment_id')->nullable()->constrained('s2s_segments')->nullOnDelete();
            $table->string('language_code', 16);
            $table->string('channel_name', 64);
            $table->string('status', 32)->default('provider_pending');
            $table->text('text_output')->nullable();
            $table->string('audio_output_path')->nullable();
            $table->json('output_meta')->nullable();
            $table->timestamps();

            $table->index(['session_id', 'language_code']);
            $table->unique(['segment_id', 'language_code']);
        });

        Schema::create('s2s_runtime_configs', function (Blueprint $table) {
            $table->id();
            $table->string('config_key')->unique();
            $table->json('config_value');
            $table->foreignId('edited_by_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
        });

        Schema::create('s2s_vocabulary_rules', function (Blueprint $table) {
            $table->id();
            $table->string('rule_type', 32)->default('replacement');
            $table->string('language_code', 16)->nullable();
            $table->string('source_phrase');
            $table->string('replacement_text')->nullable();
            $table->string('phonetic_hint')->nullable();
            $table->unsignedInteger('priority')->default(100);
            $table->boolean('is_active')->default(true);
            $table->text('notes')->nullable();
            $table->foreignId('created_by_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->index(['rule_type', 'language_code', 'is_active']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('s2s_vocabulary_rules');
        Schema::dropIfExists('s2s_runtime_configs');
        Schema::dropIfExists('s2s_outputs');

        Schema::table('s2s_segments', function (Blueprint $table) {
            $table->dropColumn(['source_language', 'status', 'translated_segments', 'engine_meta']);
        });

        Schema::table('s2s_sessions', function (Blueprint $table) {
            $table->dropColumn([
                'title',
                'mode',
                'input_source',
                'listener_scope',
                'available_target_langs',
                'audio_input_meta',
                'archive_meta',
                'fallback_meta',
                'announcement_text',
            ]);
        });
    }
};
