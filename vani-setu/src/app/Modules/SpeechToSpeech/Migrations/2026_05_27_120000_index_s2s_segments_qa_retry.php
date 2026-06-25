<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    /**
     * Index for the cross-session retry sweep:
     *   WHERE qa_state = 'failed' AND qa_attempts < N
     *   AND (qa_checked_at IS NULL OR qa_checked_at < ?)
     * Without this, the retry service does a full table scan over
     * s2s_segments every 5 minutes. With the index Postgres can seek
     * directly to qa_state='failed' rows.
     *
     * The existing (session_id, qa_state) composite stays — it covers
     * the per-session admin queries (QA summary, recheck command).
     */
    public function up(): void
    {
        Schema::table('s2s_segments', function (Blueprint $table) {
            $table->index(
                ['qa_state', 'qa_attempts', 'qa_checked_at'],
                's2s_segments_qa_retry_idx',
            );
        });
    }

    public function down(): void
    {
        Schema::table('s2s_segments', function (Blueprint $table) {
            $table->dropIndex('s2s_segments_qa_retry_idx');
        });
    }
};
