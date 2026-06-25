<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    private const QA_STATES = ['pending', 'passed', 'drift', 'corrected', 'skipped', 'failed'];

    public function up(): void
    {
        Schema::table('s2s_segments', function (Blueprint $table) {
            $table->string('qa_state', 16)->default('pending')->after('engine_meta');
            $table->decimal('qa_score', 5, 4)->nullable()->after('qa_state');
            $table->text('qa_corrected_text')->nullable()->after('qa_score');
            $table->json('qa_engine_meta')->nullable()->after('qa_corrected_text');
            $table->timestampTz('qa_checked_at')->nullable()->after('qa_engine_meta');
            $table->unsignedSmallInteger('qa_attempts')->default(0)->after('qa_checked_at');

            $table->index(['session_id', 'qa_state'], 's2s_segments_session_qa_state_idx');
        });

        if (DB::getDriverName() === 'pgsql') {
            $allowed = "'".implode("','", self::QA_STATES)."'";
            DB::statement("ALTER TABLE s2s_segments ADD CONSTRAINT s2s_segments_qa_state_chk CHECK (qa_state IN ({$allowed}))");
        }
    }

    public function down(): void
    {
        if (DB::getDriverName() === 'pgsql') {
            DB::statement('ALTER TABLE s2s_segments DROP CONSTRAINT IF EXISTS s2s_segments_qa_state_chk');
        }
        Schema::table('s2s_segments', function (Blueprint $table) {
            $table->dropIndex('s2s_segments_session_qa_state_idx');
            $table->dropColumn([
                'qa_state',
                'qa_score',
                'qa_corrected_text',
                'qa_engine_meta',
                'qa_checked_at',
                'qa_attempts',
            ]);
        });
    }
};
