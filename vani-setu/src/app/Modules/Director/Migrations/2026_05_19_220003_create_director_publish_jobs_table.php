<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('director_publish_jobs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('window_id')->unique()->constrained('js_windows')->cascadeOnDelete();
            $table->foreignId('director_user_id')->nullable()->constrained('users');
            $table->timestampTz('queued_at');
            $table->timestampTz('ran_at')->nullable();
            $table->timestampTz('finished_at')->nullable();
            $table->enum('status', ['queued', 'crc_running', 'sansad_pushing', 'published', 'failed'])->default('queued');
            $table->string('crc_pdf_path')->nullable();
            $table->string('sansad_url')->nullable();
            $table->text('last_error')->nullable();
            $table->timestampsTz();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('director_publish_jobs');
    }
};
