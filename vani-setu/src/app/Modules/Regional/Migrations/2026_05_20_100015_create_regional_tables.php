<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('regional_cases', function (Blueprint $table) {
            $table->id();
            $table->foreignId('sitting_id')->nullable()->constrained('sittings');
            $table->foreignId('slot_id')->nullable()->constrained('slots');
            $table->foreignId('block_id')->nullable()->constrained('blocks');
            $table->foreignId('requester_user_id')->nullable()->constrained('users');
            $table->foreignId('specialist_user_id')->nullable()->constrained('users');
            $table->string('source_language', 12);
            $table->string('target_language', 12)->default('hi');
            $table->string('detector', 40)->default('unicode-script');
            $table->decimal('detection_confidence', 5, 2)->default(0);
            $table->string('domain', 32)->default('parliamentary');
            $table->string('status', 32)->default('routed');
            $table->text('source_text');
            $table->text('machine_translation')->nullable();
            $table->text('specialist_translation')->nullable();
            $table->json('routing_meta')->nullable();
            $table->json('translation_meta')->nullable();
            $table->timestamps();
            $table->index(['specialist_user_id', 'source_language', 'status'], 'regional_queue_idx');
        });

        Schema::create('regional_cross_checks', function (Blueprint $table) {
            $table->id();
            $table->foreignId('case_id')->constrained('regional_cases')->cascadeOnDelete();
            $table->foreignId('reviewer_user_id')->constrained('users');
            $table->string('result', 24);
            $table->json('issues')->nullable();
            $table->unsignedTinyInteger('score')->default(100);
            $table->text('notes')->nullable();
            $table->foreignId('audit_log_id')->nullable()->constrained('audit_logs');
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('regional_cross_checks');
        Schema::dropIfExists('regional_cases');
    }
};
