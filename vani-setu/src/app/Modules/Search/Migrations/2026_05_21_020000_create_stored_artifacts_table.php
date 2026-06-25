<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('stored_artifacts', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->string('title');
            $table->string('stored_disk')->nullable();
            $table->string('storage_path')->nullable();
            $table->string('storage_uri')->nullable();
            $table->string('mime_type')->nullable();
            $table->string('extension', 32)->nullable();
            $table->string('media_family', 32)->default('binary');
            $table->string('sensitivity_classification')->default('non_sensitive');
            $table->string('source_system')->default('vani_setu');
            $table->string('source_module')->nullable();
            $table->string('subject_type')->nullable();
            $table->unsignedBigInteger('subject_id')->nullable();
            $table->foreignId('uploaded_by_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->unsignedBigInteger('size_bytes')->nullable();
            $table->string('sha256', 64)->nullable();
            $table->json('tags')->nullable();
            $table->json('metadata')->nullable();
            $table->text('metadata_text')->nullable();
            $table->longText('search_text')->nullable();
            $table->boolean('ai_eligible')->default(true);
            $table->boolean('search_eligible')->default(true);
            $table->string('classification_status')->default('pending');
            $table->string('search_status')->default('pending');
            $table->timestamp('indexed_at')->nullable();
            $table->timestamp('last_hygiene_at')->nullable();
            $table->timestamps();

            $table->index(['sensitivity_classification', 'search_status']);
            $table->index(['media_family', 'source_module']);
            $table->index(['subject_type', 'subject_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('stored_artifacts');
    }
};
