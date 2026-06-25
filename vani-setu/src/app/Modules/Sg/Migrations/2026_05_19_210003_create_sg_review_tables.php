<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('sg_reviews', function (Blueprint $table) {
            $table->id();
            $table->foreignId('window_id')->unique()->constrained('js_windows')->cascadeOnDelete();
            $table->foreignId('sg_user_id')->constrained('users');
            $table->timestampTz('opened_at')->nullable();
            $table->timestampTz('signed_at')->nullable();
            $table->string('dsc_serial', 80)->nullable();
            $table->unsignedInteger('confirmed_expunges')->default(0);
            $table->unsignedInteger('overridden_expunges')->default(0);
            $table->unsignedInteger('manual_expunges')->default(0);
            $table->foreignId('audit_log_id_open')->nullable()->constrained('audit_logs');
            $table->foreignId('audit_log_id_sign')->nullable()->constrained('audit_logs');
            $table->timestampsTz();
        });

        Schema::create('sg_manual_expunges', function (Blueprint $table) {
            $table->id();
            $table->foreignId('window_id')->constrained('js_windows')->cascadeOnDelete();
            $table->foreignId('block_id')->constrained('blocks')->cascadeOnDelete();
            $table->string('word');
            $table->text('grounds');
            $table->foreignId('added_by_sg_user_id')->constrained('users');
            $table->foreignId('audit_log_id')->constrained('audit_logs');
            $table->timestampsTz();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('sg_manual_expunges');
        Schema::dropIfExists('sg_reviews');
    }
};
