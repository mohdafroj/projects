<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('audit_logs', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->char('prev_hash', 64)->nullable();
            $table->char('this_hash', 64)->unique();
            $table->foreignId('actor_id')->nullable()->constrained('users');
            $table->string('actor_role');
            $table->string('chain_segment')->default('on_record')->index();
            $table->string('action')->index();
            $table->string('subject_type')->nullable();
            $table->string('subject_id')->nullable()->index();
            $table->jsonb('payload');
            $table->string('request_ip', 45);
            $table->string('request_ua', 255);
            $table->uuid('request_id')->index();
            $table->timestampTz('created_at')->index();

            $table->index('actor_id');
        });

        if (DB::getDriverName() === 'pgsql') {
            DB::statement(
                "ALTER TABLE audit_logs ADD CONSTRAINT audit_logs_this_hash_hex CHECK (this_hash ~ '^[0-9a-f]{64}$')"
            );

            DB::statement(
                "ALTER TABLE audit_logs ADD CONSTRAINT audit_logs_prev_hash_hex CHECK (prev_hash IS NULL OR prev_hash ~ '^[0-9a-f]{64}$')"
            );
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('audit_logs');
    }
};
