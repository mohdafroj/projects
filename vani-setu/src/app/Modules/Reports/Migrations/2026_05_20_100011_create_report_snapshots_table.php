<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('report_snapshots', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->json('filters');
            $table->json('chart_data');
            $table->json('export_meta')->nullable();
            $table->foreignId('captured_by_user_id')->constrained('users');
            $table->foreignId('captured_audit_log_id')->nullable()->constrained('audit_logs');
            $table->timestamp('captured_at');
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('report_snapshots');
    }
};
