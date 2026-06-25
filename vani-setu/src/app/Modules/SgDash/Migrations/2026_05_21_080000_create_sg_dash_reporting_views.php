<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        if (DB::getDriverName() !== 'pgsql') {
            return;
        }

        DB::statement(<<<'SQL'
CREATE OR REPLACE VIEW bi_sg_window_fact AS
WITH expunge_counts AS (
    SELECT
        ec.window_id,
        COUNT(*) FILTER (WHERE ec.state = 'pending') AS pending_expunges,
        COUNT(*) FILTER (WHERE ec.state = 'confirmed') AS confirmed_expunges,
        COUNT(*) FILTER (WHERE ec.state = 'overridden') AS overridden_expunges
    FROM expunge_candidates ec
    GROUP BY ec.window_id
),
edit_counts AS (
    SELECT
        se.window_id,
        COUNT(*) FILTER (WHERE se.state = 'pending') AS suggested_edits_pending,
        COUNT(*) FILTER (WHERE se.state = 'accepted') AS suggested_edits_accepted,
        COUNT(*) FILTER (WHERE se.state = 'declined') AS suggested_edits_declined
    FROM suggested_edits se
    GROUP BY se.window_id
),
manual_counts AS (
    SELECT
        sme.window_id,
        COUNT(*) AS manual_expunges
    FROM sg_manual_expunges sme
    GROUP BY sme.window_id
),
handoff_counts AS (
    SELECT
        h.window_id,
        COUNT(*) AS handoff_count,
        MAX(h.sent_at) AS last_sent_at,
        MAX(h.returned_at) AS last_returned_at
    FROM js_sg_handoffs h
    GROUP BY h.window_id
)
SELECT
    w.id AS window_id,
    s.id AS sitting_id,
    s.sitting_date,
    s.session_no,
    s.sitting_no,
    w.window_code,
    w.starts_at_offset_ms,
    w.duration_ms,
    w.status,
    COALESCE(ec.pending_expunges, 0) AS pending_expunges,
    COALESCE(ec.confirmed_expunges, 0) AS confirmed_expunges,
    COALESCE(ec.overridden_expunges, 0) AS overridden_expunges,
    COALESCE(mc.manual_expunges, 0) AS manual_expunges,
    COALESCE(ed.suggested_edits_pending, 0) AS suggested_edits_pending,
    COALESCE(ed.suggested_edits_accepted, 0) AS suggested_edits_accepted,
    COALESCE(ed.suggested_edits_declined, 0) AS suggested_edits_declined,
    COALESCE(hc.handoff_count, 0) AS handoff_count,
    hc.last_sent_at,
    hc.last_returned_at,
    CASE
        WHEN w.status = 'sent_to_sg' AND hc.last_sent_at IS NOT NULL
            THEN GREATEST(0, FLOOR(EXTRACT(EPOCH FROM (now() - hc.last_sent_at)) / 60))::int
        ELSE NULL
    END AS current_age_minutes,
    CASE
        WHEN w.status <> 'sent_to_sg' OR hc.last_sent_at IS NULL THEN 'closed'
        WHEN now() - hc.last_sent_at <= interval '30 minutes' THEN '0_30'
        WHEN now() - hc.last_sent_at <= interval '60 minutes' THEN '31_60'
        WHEN now() - hc.last_sent_at <= interval '120 minutes' THEN '61_120'
        ELSE 'over_120'
    END AS age_bucket
FROM js_windows w
JOIN sittings s ON s.id = w.sitting_id
LEFT JOIN expunge_counts ec ON ec.window_id = w.id
LEFT JOIN edit_counts ed ON ed.window_id = w.id
LEFT JOIN manual_counts mc ON mc.window_id = w.id
LEFT JOIN handoff_counts hc ON hc.window_id = w.id;
SQL);

        DB::statement(<<<'SQL'
CREATE OR REPLACE VIEW bi_sg_expunge_fact AS
SELECT
    ec.id AS expunge_id,
    w.id AS window_id,
    s.id AS sitting_id,
    s.sitting_date,
    s.session_no,
    s.sitting_no,
    w.window_code,
    w.status AS window_status,
    ec.state,
    ec.word,
    ec.grounds,
    ec.master_db_ref,
    b.id AS block_id,
    b.slot_id,
    b.original_lang,
    b.chief_lang,
    b.ai_action,
    LEFT(b.text, 240) AS block_excerpt,
    ec.created_at
FROM expunge_candidates ec
JOIN js_windows w ON w.id = ec.window_id
JOIN sittings s ON s.id = w.sitting_id
JOIN blocks b ON b.id = ec.block_id;
SQL);
    }

    public function down(): void
    {
        if (DB::getDriverName() !== 'pgsql') {
            return;
        }

        DB::statement('DROP VIEW IF EXISTS bi_sg_expunge_fact');
        DB::statement('DROP VIEW IF EXISTS bi_sg_window_fact');
    }
};
