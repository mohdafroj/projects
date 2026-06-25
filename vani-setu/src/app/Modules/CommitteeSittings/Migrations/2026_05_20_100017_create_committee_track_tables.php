<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('committees', function (Blueprint $table) {
            $table->id();
            $table->string('code')->unique();
            $table->string('name');
            $table->string('type');
            $table->text('terms_of_reference')->nullable();
            $table->timestamps();
        });

        Schema::create('committee_sittings', function (Blueprint $table) {
            $table->id();
            $table->foreignId('committee_id')->constrained('committees')->cascadeOnDelete();
            $table->foreignId('sitting_id')->nullable()->constrained('sittings')->nullOnDelete();
            $table->string('meeting_no');
            $table->dateTime('scheduled_at');
            $table->string('venue')->nullable();
            $table->string('status')->default('scheduled');
            $table->boolean('in_camera_default')->default(false);
            $table->json('witnesses')->nullable();
            $table->json('observers')->nullable();
            $table->timestamps();
        });

        Schema::create('committee_participants', function (Blueprint $table) {
            $table->id();
            $table->foreignId('committee_id')->constrained('committees')->cascadeOnDelete();
            $table->foreignId('user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('member_id')->nullable()->constrained('members')->nullOnDelete();
            $table->string('role');
            $table->timestamps();
            $table->unique(['committee_id', 'user_id', 'role']);
        });

        Schema::create('committee_documents', function (Blueprint $table) {
            $table->id();
            $table->foreignId('committee_sitting_id')->constrained('committee_sittings')->cascadeOnDelete();
            $table->foreignId('chair_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('prepared_by_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('document_type');
            $table->string('status')->default('draft');
            $table->string('title');
            $table->longText('body')->nullable();
            $table->boolean('in_camera')->default(false);
            $table->timestamp('chair_signed_at')->nullable();
            $table->timestamp('laid_at')->nullable();
            $table->string('prism_archive_ref')->nullable();
            $table->string('dsc_serial')->nullable();
            $table->timestamps();
        });

        Schema::create('committee_workflow_events', function (Blueprint $table) {
            $table->id();
            $table->foreignId('committee_sitting_id')->constrained('committee_sittings')->cascadeOnDelete();
            $table->foreignId('committee_document_id')->nullable()->constrained('committee_documents')->cascadeOnDelete();
            $table->foreignId('actor_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('from_stage')->nullable();
            $table->string('to_stage');
            $table->text('note')->nullable();
            $table->foreignId('audit_log_id')->nullable()->constrained('audit_logs')->nullOnDelete();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('committee_workflow_events');
        Schema::dropIfExists('committee_documents');
        Schema::dropIfExists('committee_participants');
        Schema::dropIfExists('committee_sittings');
        Schema::dropIfExists('committees');
    }
};
