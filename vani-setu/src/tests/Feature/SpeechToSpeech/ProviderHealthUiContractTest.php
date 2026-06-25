<?php

namespace Tests\Feature\SpeechToSpeech;

use Tests\TestCase;

class ProviderHealthUiContractTest extends TestCase
{
    public function test_provider_health_badges_render_watch_and_collecting_states_distinctly(): void
    {
        $components = file_get_contents(public_path('vanisetu-speech-to-speech/components.jsx'));

        $this->assertIsString($components);
        $this->assertStringContainsString('if (document.hidden) return;', $components);
        $this->assertStringContainsString('const onVisibility = () => { if (!document.hidden) fetchOnce(); };', $components);
        $this->assertStringContainsString('document.addEventListener("visibilitychange", onVisibility);', $components);
        $this->assertStringContainsString('document.removeEventListener("visibilitychange", onVisibility);', $components);
        $this->assertStringContainsString('master_orchestrator: "Master"', $components);
        $this->assertStringContainsString('s2s_latency_slo: "Latency"', $components);
        $this->assertStringContainsString('s2s_client_errors: "Client"', $components);
        $this->assertStringContainsString('"master_orchestrator", "vani_setu_app"', $components);
        $this->assertStringContainsString('"s2s_error_rate", "s2s_client_errors", "s2s_latency_slo"', $components);
        $this->assertStringContainsString('p.recent_errors != null', $components);
        $this->assertStringContainsString('p.by_language?.[0]', $components);
        $this->assertStringContainsString('top language: ${p.by_language[0].language_code} (${p.by_language[0].count})', $components);
        $this->assertStringContainsString('p.thresholds ?', $components);
        $this->assertStringContainsString('thresholds: total ${p.thresholds.total_degraded}, kind ${p.thresholds.live_kind_degraded}, language ${p.thresholds.language_degraded}', $components);
        $this->assertStringContainsString('p.threshold_breaches?.[0]', $components);
        $this->assertStringContainsString('breach: ${p.threshold_breaches[0].type}', $components);
        $this->assertStringContainsString('p.cataloged_source_audio != null', $components);
        $this->assertStringContainsString('source audio: ${p.cataloged_source_audio} cataloged, ${p.active_cataloged_source_audio ?? 0} active, ${p.pruned_cataloged_source_audio ?? 0} pruned', $components);
        $this->assertStringContainsString('p.ready_for_live != null', $components);
        $this->assertStringContainsString('p.signal ? `\\nsignal: ${p.signal}` : ""', $components);
        $this->assertStringContainsString('p.latest_error?.message', $components);
        $this->assertStringContainsString('window.dispatchEvent(new CustomEvent("vani-s2s-provider-health", { detail: d }));', $components);
        $this->assertStringContainsString('watch:    { bg:', $components);
        $this->assertStringContainsString('collecting:{ bg:', $components);
        $this->assertStringContainsString('["up", "down", "degraded", "watch"].includes(p.status)', $components);
        $this->assertStringContainsString('colors[p.status] || colors.unknown', $components);
    }

    public function test_top_bar_pipeline_status_includes_application_and_latency_health(): void
    {
        $app = file_get_contents(public_path('vanisetu-speech-to-speech/app.jsx'));

        $this->assertIsString($app);
        $this->assertStringContainsString('window.addEventListener("vani-s2s-provider-health", updateFromHealth);', $app);
        $this->assertStringContainsString('window.removeEventListener("vani-s2s-provider-health", updateFromHealth);', $app);
        $this->assertStringNotContainsString('fetch("/speech-to-speech/providers/health"', $app);
        $this->assertStringContainsString('const masterStatus = d.providers?.master_orchestrator?.status;', $app);
        $this->assertStringContainsString('const appStatus = d.providers?.vani_setu_app?.status;', $app);
        $this->assertStringContainsString('const latencyStatus = d.providers?.s2s_latency_slo?.status;', $app);
        $this->assertStringContainsString('const clientErrorStatus = d.providers?.s2s_client_errors?.status;', $app);
        $this->assertStringContainsString('masterStatus === "degraded"', $app);
        $this->assertStringContainsString('masterStatus === "watch"', $app);
        $this->assertStringContainsString('latencyStatus === "watch"', $app);
        $this->assertStringContainsString('clientErrorStatus === "watch"', $app);
        $this->assertStringContainsString('Pipeline · client watch', $app);
        $this->assertStringContainsString('Pipeline · latency watch', $app);
        $this->assertStringContainsString('Pipeline · degraded', $app);
    }

    public function test_transcript_panel_renders_exported_segment_replay_anchors(): void
    {
        $components = file_get_contents(public_path('vanisetu-speech-to-speech/components.jsx'));

        $this->assertIsString($components);
        $this->assertStringContainsString('const segmentAnchorId = (seg) => seg.segmentId ? `s2s-segment-${seg.segmentId}` : undefined;', $components);
        $this->assertStringContainsString('id={segmentAnchorId(seg)}', $components);
        $this->assertStringContainsString('data-segment-id={seg.segmentId || undefined}', $components);
        $this->assertStringContainsString('data-segment-start={seg.audioStorage?.start_ms ?? Math.round((seg.startSec || 0) * 1000)}', $components);
        $this->assertStringContainsString('data-segment-end={seg.audioStorage?.end_ms ?? Math.round(((seg.startSec || 0) + (seg.durationSec || 0)) * 1000)}', $components);
        $this->assertStringContainsString('seg.audioStorage?.pruned', $components);
        $this->assertStringContainsString('const activeStoredChunks = chunks.filter(c => c.audioStorage?.path).length;', $components);
        $this->assertStringContainsString('const prunedAudioChunks = chunks.filter(c => c.audioStorage?.pruned).length;', $components);
        $this->assertStringContainsString('audio active:', $components);
        $this->assertStringContainsString('audio pruned:', $components);
        $this->assertStringContainsString('{label} · pruned', $components);
        $this->assertStringContainsString('Source audio pruned by ${seg.audioStorage.pruned_reason || "retention policy"}', $components);
        $this->assertStringContainsString('data-edit-anchor={segmentAnchorId(seg)}', $components);
    }

    public function test_benchmark_panel_surfaces_audio_linkage_and_pruning_rates(): void
    {
        $components = file_get_contents(public_path('vanisetu-speech-to-speech/components.jsx'));

        $this->assertIsString($components);
        $this->assertStringContainsString('const fmtPct = (v) => v == null ? "—" : `${Math.round(Number(v) * 100)}%`;', $components);
        $this->assertStringContainsString('if (document.hidden) return;', $components);
        $this->assertStringContainsString('const onVisibility = () => { if (!document.hidden) fetchOnce(); };', $components);
        $this->assertStringContainsString('document.addEventListener("visibilitychange", onVisibility);', $components);
        $this->assertStringContainsString('document.removeEventListener("visibilitychange", onVisibility);', $components);
        $this->assertStringContainsString('audio linked {fmtPct(metrics.source_audio_linkage_rate)}', $components);
        $this->assertStringContainsString('active {fmtPct(metrics.source_audio_active_rate)}', $components);
        $this->assertStringContainsString('pruned {fmtPct(metrics.source_audio_pruned_rate)}', $components);
        $this->assertStringContainsString('benchmark_comparison', $components);
        $this->assertStringContainsString('Vani avg', $components);
        $this->assertStringContainsString('Best avg', $components);
        $this->assertStringContainsString('Delta', $components);
        $this->assertStringContainsString('Gap to best reference', $components);
        $this->assertStringContainsString('Math.abs(gap)', $components);
    }

    public function test_analog_clock_pauses_hidden_tab_updates(): void
    {
        $components = file_get_contents(public_path('vanisetu-speech-to-speech/components.jsx'));

        $this->assertIsString($components);
        $this->assertStringContainsString('const tick = () => {', $components);
        $this->assertStringContainsString('if (!document.hidden) setNow(new Date());', $components);
        $this->assertStringContainsString('const onVisibility = () => { if (!document.hidden) setNow(new Date()); };', $components);
        $this->assertStringContainsString('const id = setInterval(tick, 1000);', $components);
        $this->assertStringContainsString('document.addEventListener("visibilitychange", onVisibility);', $components);
        $this->assertStringContainsString('document.removeEventListener("visibilitychange", onVisibility);', $components);
    }
}
