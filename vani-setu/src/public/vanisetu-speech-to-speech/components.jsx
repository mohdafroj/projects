// components.jsx — UI primitives for Vanisetu

const { useState, useEffect, useRef, useMemo } = React;

// ── tiny svg icons ──────────────────────────────────────────
// Icons kept here are referenced by components.jsx (Settings, Book),
// glossary.jsx (Plus, Trash), and app.jsx (Stop, Reset). Iter-18b/19
// pruned Mic / Vol / Pause / Skip / Down — they had no remaining
// consumers after transcript.jsx was deleted.
const Icon = {
  Settings: (p) => (
    <svg viewBox="0 0 24 24" width="14" height="14" fill="none" stroke="currentColor" strokeWidth="1.6" {...p}>
      <circle cx="12" cy="12" r="3"/>
      <path d="M19.4 15a1.65 1.65 0 0 0 .33 1.82l.06.06a2 2 0 1 1-2.83 2.83l-.06-.06a1.65 1.65 0 0 0-1.82-.33 1.65 1.65 0 0 0-1 1.51V21a2 2 0 1 1-4 0v-.09a1.65 1.65 0 0 0-1-1.51 1.65 1.65 0 0 0-1.82.33l-.06.06a2 2 0 1 1-2.83-2.83l.06-.06a1.65 1.65 0 0 0 .33-1.82 1.65 1.65 0 0 0-1.51-1H3a2 2 0 1 1 0-4h.09a1.65 1.65 0 0 0 1.51-1 1.65 1.65 0 0 0-.33-1.82l-.06-.06a2 2 0 1 1 2.83-2.83l.06.06a1.65 1.65 0 0 0 1.82.33h0a1.65 1.65 0 0 0 1-1.51V3a2 2 0 1 1 4 0v.09a1.65 1.65 0 0 0 1 1.51h0a1.65 1.65 0 0 0 1.82-.33l.06-.06a2 2 0 1 1 2.83 2.83l-.06.06a1.65 1.65 0 0 0-.33 1.82v0a1.65 1.65 0 0 0 1.51 1H21a2 2 0 1 1 0 4h-.09a1.65 1.65 0 0 0-1.51 1z"/>
    </svg>
  ),
  Book: (p) => (
    <svg viewBox="0 0 24 24" width="14" height="14" fill="none" stroke="currentColor" strokeWidth="1.6" {...p}>
      <path d="M4 19.5A2.5 2.5 0 0 1 6.5 17H20"/>
      <path d="M4 4.5A2.5 2.5 0 0 1 6.5 2H20v20H6.5A2.5 2.5 0 0 1 4 19.5z"/>
      <path d="M8 7h8M8 11h6"/>
    </svg>
  ),
  Plus:    (p) => (<svg viewBox="0 0 24 24" width="14" height="14" fill="none" stroke="currentColor" strokeWidth="2" {...p}><path d="M12 5v14M5 12h14"/></svg>),
  Trash:   (p) => (<svg viewBox="0 0 24 24" width="11" height="11" fill="none" stroke="currentColor" strokeWidth="1.6" {...p}><path d="M3 6h18M8 6V4a2 2 0 0 1 2-2h4a2 2 0 0 1 2 2v2M19 6l-1 14a2 2 0 0 1-2 2H8a2 2 0 0 1-2-2L5 6"/></svg>),
  Stop:    (p) => (<svg viewBox="0 0 24 24" width="16" height="16" fill="currentColor" {...p}><rect x="6" y="6" width="12" height="12" rx="1.5"/></svg>),
  Reset:   (p) => (<svg viewBox="0 0 24 24" width="14" height="14" fill="none" stroke="currentColor" strokeWidth="1.6" {...p}><path d="M3 12a9 9 0 1 0 3-6.7L3 8"/><path d="M3 3v5h5"/></svg>),
};

// ── top bar with brand + session time + status ──────────────
function ProviderHealth() {
  const [providers, setProviders] = useState({});
  const [lastUpdated, setLastUpdated] = useState(null);

  useEffect(() => {
    let alive = true;
    const fetchOnce = async () => {
      if (document.hidden) return;
      try {
        const r = await fetch("/speech-to-speech/providers/health", { headers: { "Accept": "application/json" } });
        if (!r.ok) return;
        const d = await r.json();
        if (!alive) return;
        setProviders(d.providers || {});
        window.dispatchEvent(new CustomEvent("vani-s2s-provider-health", { detail: d }));
        setLastUpdated(new Date());
      } catch (e) {/* ignore */}
    };
    fetchOnce();
    const onVisibility = () => { if (!document.hidden) fetchOnce(); };
    document.addEventListener("visibilitychange", onVisibility);
    const id = setInterval(fetchOnce, 15000);
    return () => {
      alive = false;
      clearInterval(id);
      document.removeEventListener("visibilitychange", onVisibility);
    };
  }, []);

  const labels = {
    master_orchestrator: "Master",
    vani_setu_app: "App",
    s2s_db: "DB",
    audio_archive: "Archive",
    s2s_error_rate: "Errors",
    s2s_client_errors: "Client",
    s2s_latency_slo: "Latency",
    s2s_qa_recheck: "QA",
    sarvam_stt: "STT",
    sarvam_translate: "MT",
    sarvam_tts: "TTS",
    indictrans2: "IndicT2",
  };
  // Sarvam STT → MT → TTS in pipeline order, then the fallback at the end.
  const order = ["master_orchestrator", "vani_setu_app", "s2s_db", "audio_archive", "s2s_error_rate", "s2s_client_errors", "s2s_latency_slo", "s2s_qa_recheck", "sarvam_stt", "sarvam_translate", "sarvam_tts", "indictrans2"];
  const colors = {
    up:       { bg: "rgba(30,199,138,0.18)", dot: "#1ec78a", fg: "#1ec78a" },
    down:     { bg: "rgba(255,93,93,0.20)",  dot: "#ff5d5d", fg: "#ff8a8a" },
    degraded: { bg: "rgba(255,138,31,0.22)", dot: "#ff8a1f", fg: "#ffb066" },
    watch:    { bg: "rgba(255,204,102,0.16)", dot: "#ffcc66", fg: "#ffd27a" },
    collecting:{ bg: "rgba(122,108,255,0.14)", dot: "#a99dff", fg: "#c2bbff" },
    idle:     { bg: "rgba(255,255,255,0.06)", dot: "rgba(255,255,255,0.4)", fg: "rgba(255,255,255,0.55)" },
    unknown:  { bg: "rgba(255,255,255,0.04)", dot: "rgba(255,255,255,0.25)", fg: "rgba(255,255,255,0.4)" },
  };

  const pill = (key) => {
    const p = providers[key] || { status: "unknown" };
    const c = colors[p.status] || colors.unknown;
    const tip = `${labels[key]} (${key})  ·  ${p.status}` +
      (p.last_ok_seconds_ago != null ? `\nlast ok: ${Math.round(p.last_ok_seconds_ago)}s ago` : "") +
      (p.last_fail_seconds_ago != null ? `\nlast fail: ${Math.round(p.last_fail_seconds_ago)}s ago` : "") +
      (p.recent_errors != null ? `\nrecent errors: ${p.recent_errors}` : "") +
      (p.by_language?.[0] ? `\ntop language: ${p.by_language[0].language_code} (${p.by_language[0].count})` : "") +
      (p.thresholds ? `\nthresholds: total ${p.thresholds.total_degraded}, kind ${p.thresholds.live_kind_degraded}, language ${p.thresholds.language_degraded}` : "") +
      (p.threshold_breaches?.[0] ? `\nbreach: ${p.threshold_breaches[0].type} ${p.threshold_breaches[0].kind || p.threshold_breaches[0].language_code || ""} ${p.threshold_breaches[0].count}/${p.threshold_breaches[0].threshold}` : "") +
      (p.cataloged_source_audio != null ? `\nsource audio: ${p.cataloged_source_audio} cataloged, ${p.active_cataloged_source_audio ?? 0} active, ${p.pruned_cataloged_source_audio ?? 0} pruned` : "") +
      (p.ready_for_live != null ? `\nready for live: ${p.ready_for_live ? "yes" : "no"}` : "") +
      (p.signal ? `\nsignal: ${p.signal}` : "") +
      (p.latest_error?.message ? `\nlatest: ${p.latest_error.message}` : "") +
      (p.last_error ? `\nlast error: ${p.last_error}` : "");
    return (
      <span key={key} title={tip} style={{
        display: "inline-flex", alignItems: "center", gap: 4,
        padding: "2px 6px", borderRadius: 999,
        background: c.bg, color: c.fg,
        fontFamily: "var(--f-mono)", fontSize: 10, letterSpacing: "0.04em",
      }}>
        <span style={{
          width: 6, height: 6, borderRadius: 999, background: c.dot,
          boxShadow: ["up", "down", "degraded", "watch"].includes(p.status) ? `0 0 4px ${c.dot}` : "none",
        }}/>
        {labels[key]}
      </span>
    );
  };

  return (
    <div style={{display: "flex", gap: 4, flexWrap: "wrap", justifyContent: "center"}}>
      {order.map(pill)}
    </div>
  );
}

function BenchmarkPanel() {
  const [summary, setSummary] = useState(null);
  const [error, setError] = useState(null);

  useEffect(() => {
    let alive = true;
    const fetchOnce = async () => {
      if (document.hidden) return;
      try {
        const r = await fetch("/speech-to-speech/benchmarks/summary", { headers: { "Accept": "application/json" } });
        if (!r.ok) throw new Error(`benchmark ${r.status}`);
        const data = await r.json();
        if (!alive) return;
        setSummary(data);
        setError(null);
      } catch (e) {
        if (alive) setError(e.message || "benchmark unavailable");
      }
    };
    fetchOnce();
    const onVisibility = () => { if (!document.hidden) fetchOnce(); };
    document.addEventListener("visibilitychange", onVisibility);
    const id = setInterval(fetchOnce, 30000);
    return () => {
      alive = false;
      clearInterval(id);
      document.removeEventListener("visibilitychange", onVisibility);
    };
  }, []);

  const systems = summary?.systems || [];
  const metrics = summary?.metrics || {};
  const comparison = summary?.benchmark_comparison || {};
  const readiness = summary?.language_readiness || metrics.language_readiness || {};
  const rolloutAgents = summary?.rollout_agents || [];
  const stageLatency = metrics.stage_latency_ms || {};
  const stageOrder = ["first_byte", "stt", "translation", "tts"];
  const scoreKeys = [
    ["latency", "Latency"],
    ["asr_quality", "ASR"],
    ["translation", "Translate"],
    ["tts", "TTS"],
    ["reliability", "Stable"],
  ];
  const fmtMs = (v) => v == null ? "—" : `${Math.round(v)} ms`;
  const fmtPct = (v) => v == null ? "—" : `${Math.round(Number(v) * 100)}%`;
  const gradeColor = (grade) => ({
    A: "#1ec78a",
    B: "#8bd46e",
    C: "#ffcc66",
    D: "#ff8a1f",
    E: "#ff5d8a",
  }[grade] || "var(--text-3)");

  return (
    <section className="benchmark-panel" aria-label="Benchmark grading">
      <div className="benchmark-head">
        <div>
          <h4>Benchmark grade</h4>
          <span>Measured deployment vs reference targets</span>
        </div>
        <div className="benchmark-metrics">
          <span>p50 {fmtMs(metrics.p50_latency_ms)}</span>
          <span>p95 {fmtMs(metrics.p95_latency_ms)}</span>
          <span>{metrics.segments_sampled ?? 0} seg</span>
          <span>audio linked {fmtPct(metrics.source_audio_linkage_rate)}</span>
          <span>active {fmtPct(metrics.source_audio_active_rate)}</span>
          <span>pruned {fmtPct(metrics.source_audio_pruned_rate)}</span>
        </div>
      </div>
      <div className="benchmark-comparison" aria-label="Benchmark comparison summary">
        <span>
          <b>Vani avg</b>
          <em>{comparison.measured_average == null ? "—" : comparison.measured_average}</em>
        </span>
        <span>
          <b>Best avg</b>
          <em>{comparison.best_reference_average == null ? "—" : comparison.best_reference_average}</em>
        </span>
        <span data-ahead={(comparison.best_reference_average != null && comparison.measured_average != null && comparison.measured_average >= comparison.best_reference_average) ? "true" : "false"}>
          <b>Delta</b>
          <em>{comparison.measured_average == null || comparison.best_reference_average == null ? "—" : `${Math.abs(comparison.best_reference_average - comparison.measured_average)} pts`}</em>
        </span>
      </div>
      <div className="benchmark-stage-latency" aria-label="Stage latency">
        {stageOrder.map((key) => {
          const item = stageLatency[key] || {};
          const active = metrics.bottleneck_stage === key;
          return (
            <span key={key} data-active={active ? "true" : "false"}>
              <b>{item.label || key}</b>
              <em>p95 {fmtMs(item.p95_ms)}</em>
            </span>
          );
        })}
      </div>
      <div className="benchmark-gap" aria-label="Gap to best reference">
        {(scoreKeys || []).map(([key, label]) => {
          const measured = systems[0]?.scores?.[key];
          const reference = comparison.best_reference_scores?.[key];
          const gap = comparison.measured_gaps?.[key];
          const ahead = gap != null && gap < 0;
          return (
            <span key={key} data-ahead={ahead ? "true" : "false"}>
              <b>{label}</b>
              <em>{measured == null ? "—" : measured}</em>
              <i>{reference == null ? "ref —" : `best ${reference}`}{gap == null ? "" : ` · ${ahead ? "ahead" : "gap"} ${Math.abs(gap)}`}</i>
            </span>
          );
        })}
      </div>
      <div className="benchmark-readiness" aria-label="Language readiness">
        <span>
          <b>Scheduled</b>
          <em>{readiness.scheduled_languages_registered ?? 0}/{readiness.scheduled_languages_required ?? 22}</em>
        </span>
        <span data-warn={(readiness.text_only_scheduled_languages || []).length ? "true" : "false"}>
          <b>Audio</b>
          <em>{readiness.scheduled_audio_output_languages ?? 0}/{readiness.scheduled_languages_registered ?? 0}</em>
        </span>
        <span data-warn={readiness.text_only_audio_fallback_ready ? "false" : "true"}>
          <b>Fallback</b>
          <em>{readiness.text_only_audio_fallback_ready ? (readiness.audible_fallback_language || "ready") : "missing"}</em>
        </span>
        <span>
          <b>English</b>
          <em>{readiness.priority_outputs?.["en-IN"]?.audio_output ? "voice ready" : "text only"}</em>
        </span>
        <span>
          <b>Hindi</b>
          <em>{readiness.priority_outputs?.["hi-IN"]?.audio_output ? "voice ready" : "text only"}</em>
        </span>
      </div>
      <div className="rollout-agents" aria-label="Rollout agents">
        {rolloutAgents.map(agent => (
          <span key={agent.key} data-status={agent.status || "collecting"} title={agent.focus || ""}>
            <b>{agent.label}</b>
            <em>{agent.status || "collecting"} · {agent.score ?? "—"}</em>
            <i>{agent.signal || "Collecting signals"}</i>
          </span>
        ))}
      </div>
      {error && !summary ? (
        <div className="benchmark-empty">Benchmark unavailable</div>
      ) : systems.length === 0 ? (
        <div className="benchmark-empty">Collecting benchmark signals</div>
      ) : (
        <div className="benchmark-grid">
          {systems.map(system => (
            <div className="benchmark-row" key={system.name} data-kind={system.kind}>
              <div className="benchmark-name">
                <strong>{system.name}</strong>
                <span>{system.kind === "measured" ? "measured" : "reference target"}</span>
              </div>
              <div className="benchmark-grade" style={{color: gradeColor(system.grade)}}>{system.grade}</div>
              <div className="benchmark-bars">
                {scoreKeys.map(([key, label]) => {
                  const score = system.scores?.[key];
                  const width = score == null ? 0 : Math.max(0, Math.min(100, score));
                  return (
                    <div className="benchmark-bar" key={key} title={`${label}: ${score ?? "N/A"}`}>
                      <span>{label}</span>
                      <i><b style={{width: `${width}%`}} /></i>
                    </div>
                  );
                })}
              </div>
            </div>
          ))}
        </div>
      )}
    </section>
  );
}

function AnalogClock({ size = 56 }) {
  const [now, setNow] = useState(() => new Date());
  useEffect(() => {
    const tick = () => {
      if (!document.hidden) setNow(new Date());
    };
    const onVisibility = () => { if (!document.hidden) setNow(new Date()); };
    const id = setInterval(tick, 1000);
    document.addEventListener("visibilitychange", onVisibility);
    return () => {
      clearInterval(id);
      document.removeEventListener("visibilitychange", onVisibility);
    };
  }, []);
  const h = now.getHours() % 12;
  const m = now.getMinutes();
  const s = now.getSeconds();
  const hourAngle = (h * 30) + (m * 0.5);
  const minAngle = m * 6;
  const secAngle = s * 6;
  const cx = size / 2, cy = size / 2;
  const r = size / 2 - 2;
  const tick = (i) => {
    const a = (i * 30) * Math.PI / 180;
    const x1 = cx + Math.sin(a) * (r - 4);
    const y1 = cy - Math.cos(a) * (r - 4);
    const x2 = cx + Math.sin(a) * r;
    const y2 = cy - Math.cos(a) * r;
    return <line key={i} x1={x1} y1={y1} x2={x2} y2={y2} stroke="var(--text-3)" strokeWidth={i % 3 === 0 ? 1.6 : 0.8} />;
  };
  const hand = (angle, length, width, color) => {
    const a = angle * Math.PI / 180;
    const x = cx + Math.sin(a) * length;
    const y = cy - Math.cos(a) * length;
    return <line x1={cx} y1={cy} x2={x} y2={y} stroke={color} strokeWidth={width} strokeLinecap="round" />;
  };
  const iso = now.toLocaleTimeString("en-IN", { hour12: false });
  return (
    <div style={{display: "flex", alignItems: "center", gap: 8}} title={`Server time · ${iso}`}>
      <svg width={size} height={size} style={{display: "block"}}>
        <circle cx={cx} cy={cy} r={r} fill="rgba(20,20,30,0.55)" stroke="var(--saffron)" strokeWidth="1.2" />
        {Array.from({length: 12}).map((_, i) => tick(i))}
        {hand(hourAngle, r * 0.5, 2.2, "var(--text-1)")}
        {hand(minAngle,  r * 0.72, 1.6, "var(--text-1)")}
        {hand(secAngle,  r * 0.82, 1, "var(--saffron)")}
        <circle cx={cx} cy={cy} r="2" fill="var(--saffron)" />
      </svg>
      <span className="mono" style={{fontSize: 12, color: "var(--text-2)", letterSpacing: "0.08em"}}>{iso}</span>
    </div>
  );
}

// ── mobile nav toggle (iter-18) ─────────────────────────────
// Phone-portrait (<=480px) viewports hide the entire `.side.right` rail —
// tweaks trigger, glossary trigger, and recent inserts. This hamburger
// re-exposes that rail as a slide-in drawer without requiring an app.jsx
// edit: it's portalled directly into <body> from inside TopBar (which
// app.jsx already renders). Desktop/tablet: `display:none` via styles.css.
function ensureMobileNavTogglePortal() {
  if (typeof document === "undefined") return null;
  let host = document.getElementById("mobile-nav-toggle-host");
  if (!host) {
    host = document.createElement("div");
    host.id = "mobile-nav-toggle-host";
    document.body.appendChild(host);
  }
  return host;
}

function MobileNavToggle() {
  const [open, setOpen] = useState(false);
  useEffect(() => {
    document.body.setAttribute("data-mobile-nav-open", open ? "true" : "false");
    return () => document.body.removeAttribute("data-mobile-nav-open");
  }, [open]);
  // Auto-close when the viewport widens past phone-portrait so the
  // attribute doesn't leak into desktop layout on rotate / resize.
  useEffect(() => {
    if (typeof window === "undefined" || !window.matchMedia) return;
    const mq = window.matchMedia("(max-width: 480px)");
    const handler = (e) => { if (!e.matches) setOpen(false); };
    if (mq.addEventListener) mq.addEventListener("change", handler);
    else mq.addListener(handler);
    return () => {
      if (mq.removeEventListener) mq.removeEventListener("change", handler);
      else mq.removeListener(handler);
    };
  }, []);
  return (
    <React.Fragment>
      <button
        className="mobile-nav-toggle"
        aria-label={open ? "Close side panel" : "Open side panel"}
        aria-expanded={open ? "true" : "false"}
        onClick={() => setOpen(o => !o)}
      >{open ? "✕" : "☰"}</button>
      {open && (
        <div
          className="mobile-nav-scrim"
          onClick={() => setOpen(false)}
          aria-hidden="true"
        />
      )}
    </React.Fragment>
  );
}

function MobileNavTogglePortal() {
  const host = useMemo(() => ensureMobileNavTogglePortal(), []);
  if (!host || !ReactDOM || !ReactDOM.createPortal) return null;
  return ReactDOM.createPortal(<MobileNavToggle />, host);
}

function TopBar({ recording, paused, sessionMs, apiStatus, onOpenSettings, onOpenVocabulary }) {
  // Note: state/apiStatus/ProviderHealth are intentionally kept in the
  // logic chain so the underlying health polls + audit signals continue
  // to fire — they're just no longer rendered as chips on the bar.
  // Iter 19 (2026-05-29): the operator-facing top bar is now minimal:
  // brand on the left, clock + session timer in the centre, action
  // buttons on the right. Idle/Recording/Paused state is implied by the
  // transport button below the wave panel.
  const min = Math.floor(sessionMs / 60000);
  const sec = Math.floor((sessionMs % 60000) / 1000);
  const ms = Math.floor((sessionMs % 1000) / 10);
  const pad = (n, w = 2) => String(n).padStart(w, "0");

  return (
    <React.Fragment>
    <MobileNavTogglePortal />
    <header className="topbar">
      <div className="brand">
        <div className="brand-mark">वा</div>
        <div className="brand-text">
          <span className="deva">वाणीसेतु</span>
          <span className="latn">Vanisetu</span>
        </div>
      </div>

      <div className="topbar-center" style={{display: "flex", alignItems: "center", gap: 14, justifyContent: "center"}}>
        <AnalogClock size={48} />
        <div className="tc-time mono" style={{fontSize: 18}}>
          {pad(min)}:{pad(sec)}<span className="ms">.{pad(ms)}</span>
        </div>
      </div>

      <div className="topbar-right">
        <button className="tb-btn" onClick={onOpenSettings} title="Settings">
          <Icon.Settings/>
        </button>
        <button className="tb-btn" onClick={onOpenVocabulary} title="Vocabulary rules">
          <Icon.Book/>
        </button>
      </div>
    </header>
    </React.Fragment>
  );
}

// ── dB meter (peak ladder beside the waveform) ─────────────
function DbMeter({ level }) {
  const SEGMENTS = 12;
  const lit = Math.round(level * SEGMENTS);
  return (
    <div className="db-meter">
      {Array.from({length: SEGMENTS}).map((_, i) => {
        const idx = SEGMENTS - i; // top-down
        const on = (SEGMENTS - i) <= lit;
        let cls = "";
        if (on) {
          if (idx >= SEGMENTS - 1) cls = "peak";
          else if (idx >= SEGMENTS - 3) cls = "hot";
          else cls = "on";
        }
        return <span key={i} className={cls}></span>;
      })}
    </div>
  );
}

// ── settings modal (chunk length only — Sarvam key is server-side) ─
// The live workflow (Laravel → ml-gateway → Sarvam) handles the key
// server-side; the modal exposes chunk length only. Iter-19 dropped the
// legacy `apiKey` / `onSave` props after app.jsx stopped passing them.
function SettingsModal({ open, onClose, chunkSeconds, setChunkSeconds }) {
  if (!open) return null;
  return (
    <div className="modal-back" onClick={onClose}>
      <div className="modal" onClick={e => e.stopPropagation()}>
        <div className="modal-head">
          <h3>Sarvam pipeline</h3>
          <p>Live transcription, translation, and audio playback use the configured server key.</p>
        </div>
        <div className="modal-body">
          <div className="field">
            <label>
              Chunk length
              <span className="val">{chunkSeconds.toFixed(1)}s</span>
            </label>
            <input
              type="range"
              className="slider"
              min="2" max="12" step="0.5"
              value={chunkSeconds}
              onChange={e => setChunkSeconds(parseFloat(e.target.value))}
            />
            <div style={{fontSize: 11, color: "var(--text-3)"}}>
              Shorter chunks = lower speak→hear latency (default 3s), more API calls.
            </div>
          </div>
        </div>
        <div className="modal-actions">
          <button className="btn primary" onClick={onClose}>Close</button>
        </div>
      </div>
    </div>
  );
}

// ── speaker roster (seed) ──────────────────────────────────
// Static fallback used when the /api/members fetch fails (auth lapsed,
// network blip, dev environment without the Members table seeded). The
// live roster comes from useMembersRoster() below and merges these
// presiding-officer entries in front so they're always available.
const SPEAKER_ROSTER_FALLBACK = [
  { id: "chair",        role: "Chair",             name: "Hon'ble Chairman, Rajya Sabha",        honorific: "", name_hi: "माननीय सभापति, राज्य सभा" },
  { id: "deputy-chair", role: "Deputy Chair",      name: "Hon'ble Deputy Chairman, Rajya Sabha", honorific: "", name_hi: "माननीय उप-सभापति, राज्य सभा" },
  { id: "sg",           role: "Secretary General", name: "Secretary General, Rajya Sabha",       honorific: "", name_hi: "महासचिव, राज्य सभा" },
];

// Synthetic SG entry — the members table currently has category=chair
// for presiding officers but no Secretary General row. The Sabha Setu
// Members API will add this when it lands; for now we prepend a static
// SG so the operator always has the option.
const SYNTHETIC_SG = {
  id: "sg-synthetic",
  roster_id: "SG",
  category: "secretary_general",
  name_en: "Secretary General, Rajya Sabha",
  name_hi: "महासचिव, राज्य सभा",
  role_title: "Secretary General",
  party: "",
};

// Back-compat: some callers still reach for SPEAKER_ROSTER on window.
// Keep the symbol pointing at the fallback list so existing consumers
// don't break while the live fetch boots.
const SPEAKER_ROSTER = SPEAKER_ROSTER_FALLBACK;

function normalizeMemberRow(row) {
  if (!row) return null;
  const category = String(row.category || "member").toLowerCase();
  const roleTitle = String(row.role_title || "").trim();
  const role = category === "chair"
    ? (roleTitle || "Chair")
    : category === "minister"
      ? "Minister"
      : category === "secretary_general"
        ? "Secretary General"
        : "MP";
  const nameEn = String(row.name_en || "").trim();
  const nameHi = String(row.name_hi || "").trim();
  if (!nameEn && !nameHi) return null;
  return {
    id: row.id ? `m-${row.id}` : (row.roster_id || nameEn).toLowerCase().replace(/\W+/g, "-"),
    role,
    name: nameEn || nameHi,
    name_hi: nameHi,
    honorific: "",
    party: String(row.party || "").trim(),
    category,
  };
}

// Live roster fetch from Laravel's /api/members (Capture module). The
// route requires sanctum auth, which the operator already has via the
// speech-to-speech session cookie. Falls back to the static presiding-
// officer list on any failure so the UI never shows an empty picker.
function useMembersRoster() {
  const [roster, setRoster] = useState(SPEAKER_ROSTER_FALLBACK);
  const [status, setStatus] = useState("loading");

  useEffect(() => {
    let aborted = false;
    const ac = new AbortController();
    (async () => {
      try {
        // /api/members paginates at 50/page; fetch up to 4 pages = 200
        // members which comfortably covers the 245-seat Rajya Sabha
        // upper bound. Subsequent pages stop early when ``next_page_url``
        // is null.
        const collected = [];
        for (let page = 1; page <= 6; page++) {
          const url = `/api/members?per_page=50&page=${page}`;
          const res = await fetch(url, {
            headers: { Accept: "application/json", "X-Requested-With": "XMLHttpRequest" },
            credentials: "same-origin",
            signal: ac.signal,
          });
          if (!res.ok) throw new Error(`members ${res.status}`);
          const payload = await res.json();
          const rows = Array.isArray(payload?.data) ? payload.data : [];
          for (const row of rows) {
            const m = normalizeMemberRow(row);
            if (m) collected.push(m);
          }
          if (!payload?.next_page_url) break;
        }
        if (aborted) return;
        const sgRow = normalizeMemberRow(SYNTHETIC_SG);
        const merged = [];
        if (sgRow) merged.push(sgRow);
        merged.push(...SPEAKER_ROSTER_FALLBACK);
        merged.push(...collected);
        setRoster(merged);
        setStatus(collected.length ? "live" : "fallback");
      } catch (err) {
        if (err?.name === "AbortError") return;
        try { console.warn("[s2s] members API failed, using fallback roster:", err); } catch (_) {}
        if (!aborted) setStatus("fallback");
      }
    })();
    return () => { aborted = true; ac.abort(); };
  }, []);

  return { roster, status };
}

// Searchable dropdown for speaker selection. Filters by name/role substring
// (case-insensitive). On click of a roster row, emits the canonical insert
// payload `{kind:"speaker", label, desc}` so TxInsert in transcript.jsx
// renders it without changes.
function SpeakerPicker({ onAdd, roster: rosterProp }) {
  const live = useMembersRoster();
  const list = rosterProp || live.roster;
  const [query, setQuery] = useState("");
  const [open, setOpen] = useState(false);
  const wrapRef = useRef(null);

  // close popover on outside click
  useEffect(() => {
    const onDoc = (e) => {
      if (wrapRef.current && !wrapRef.current.contains(e.target)) setOpen(false);
    };
    document.addEventListener("mousedown", onDoc);
    return () => document.removeEventListener("mousedown", onDoc);
  }, []);

  const matches = useMemo(() => {
    const q = query.trim().toLowerCase();
    if (!q) return list;
    return list.filter(s =>
      (s.name || "").toLowerCase().includes(q) ||
      (s.name_hi || "").toLowerCase().includes(q) ||
      (s.role || "").toLowerCase().includes(q) ||
      (s.party || "").toLowerCase().includes(q) ||
      (s.honorific || "").toLowerCase().includes(q)
    );
  }, [query, list]);

  const pick = (s) => {
    const label = `${s.honorific ? s.honorific + " " : ""}${s.name}`;
    const desc = s.role;
    onAdd({ kind: "speaker", label, desc });
    setQuery("");
    setOpen(false);
  };

  return (
    <div ref={wrapRef} style={{position: "relative"}}>
      <div className="field" style={{marginBottom: 6}}>
        <label>
          Speaker
          <span className="val" style={{fontSize: 10, color: "var(--text-3)"}}>
            {live.status === "live" ? `roster · ${list.length}` : live.status === "loading" ? "loading…" : "fallback"}
          </span>
        </label>
        <input
          type="text"
          value={query}
          placeholder="Search by name, role, party…"
          onChange={e => { setQuery(e.target.value); setOpen(true); }}
          onFocus={() => setOpen(true)}
          autoComplete="off"
        />
      </div>
      {open && (
        <div className="speaker-picker-pop">
          {matches.length === 0 ? (
            <div className="speaker-picker-empty">No match — refine search</div>
          ) : matches.slice(0, 80).map(s => (
            <button
              type="button"
              key={s.id}
              className="speaker-picker-item"
              onClick={() => pick(s)}
            >
              <span className="speaker-picker-name">
                {s.honorific ? s.honorific + " " : ""}{s.name}
                {s.name_hi && (
                  <span style={{marginLeft: 6, color: "var(--text-3)", fontSize: 11}}>· {s.name_hi}</span>
                )}
              </span>
              <span className="speaker-picker-role">
                {s.role}{s.party ? ` · ${s.party}` : ""}
              </span>
            </button>
          ))}
        </div>
      )}
    </div>
  );
}

// ──────────────────────────────────────────────────────────────
// TranscriptPanel — operator-facing, per-phrase entries.
//
// Each entry is one phrase chunk and shows three rows:
//   • [HH:MM:SS] · Speaker name        (mono, dim)
//   • source text                       (white, codemix from Saaras)
//   • translated text in target lang    (green, from Sarvam Mayura)
//
// Speaker comes from the most recent SpeakerPicker insert before the
// chunk. Export bar at the top hands the assembled entries to
// /speech-to-speech/export which renders PDF / DOCX / ODT server-side.
// ──────────────────────────────────────────────────────────────
function TranscriptPanel({ chunks, inserts, targetLang, outputLangLabel, sessionId, onCorrectSegment, perceivedMs }) {
  const ref = useRef(null);
  const [exporting, setExporting] = useState(null);
  const [exportError, setExportError] = useState(null);

  useEffect(() => {
    if (ref.current) ref.current.scrollTop = ref.current.scrollHeight;
  }, [chunks.length]);

  const fmtTs = (sec) => {
    const safe = Number.isFinite(sec) ? Math.max(0, sec) : 0;
    const h = Math.floor(safe / 3600);
    const m = Math.floor((safe % 3600) / 60);
    const s = Math.floor(safe % 60);
    const pad = (n) => String(n).padStart(2, "0");
    return h > 0 ? `${pad(h)}:${pad(m)}:${pad(s)}` : `${pad(m)}:${pad(s)}`;
  };

  // Build per-chunk entries with speaker resolution. A speaker insert with
  // atChunkId = N anchors all subsequent chunks until the next speaker
  // insert replaces it. The very first chunks (before any speaker has
  // been picked) get an empty speaker label.
  const entries = useMemo(() => {
    const speakerInserts = (inserts || []).filter(i => i.kind === "speaker");
    const speakerForChunk = (chunk) => {
      let active = null;
      for (const ins of speakerInserts) {
        if ((ins.atChunkId || 0) <= chunk.id) {
          active = ins;
        } else {
          break;
        }
      }
      return active;
    };
    return (chunks || [])
      .filter(c => c.sourceText || (c.perLang && c.perLang[targetLang]?.text))
      .map(c => {
        const slot = c.perLang ? c.perLang[targetLang] : null;
        const translated = slot ? (slot.text || "") : "";
        const sp = speakerForChunk(c);
        return {
          id: c.id,
          ts: c.startSec || 0,
          speakerLabel: sp ? sp.label : "",
          speakerRole: sp ? sp.desc : "",
          source: String(c.sourceText || "").trim(),
          translated: String(translated || "").trim(),
          translatedState: slot ? slot.state : "pending",
          segmentId: c.audioStorage?.segment_id || null,
        };
      });
  }, [chunks, inserts, targetLang]);

  const handleExport = useCallback(async (format) => {
    if (exporting) return;
    setExporting(format);
    setExportError(null);
    try {
      const csrf = document.querySelector('meta[name="csrf-token"]')?.getAttribute("content") || "";
      const res = await fetch("/speech-to-speech/export", {
        method: "POST",
        credentials: "same-origin",
        headers: {
          "Accept": "application/octet-stream",
          "Content-Type": "application/json",
          "X-Requested-With": "XMLHttpRequest",
          "X-CSRF-TOKEN": csrf,
        },
        body: JSON.stringify({
          format,
          session_id: sessionId || null,
          output_language: targetLang,
          output_language_label: outputLangLabel || targetLang,
          entries: entries.map(e => ({
            ts: e.ts,
            ts_label: fmtTs(e.ts),
            speaker: e.speakerLabel || "",
            speaker_role: e.speakerRole || "",
            source: e.source,
            translated: e.translated,
          })),
        }),
      });
      if (!res.ok) {
        const text = await res.text().catch(() => "");
        throw new Error(`export ${res.status}: ${text.slice(0, 160) || res.statusText}`);
      }
      const blob = await res.blob();
      const cd = res.headers.get("Content-Disposition") || "";
      const m = cd.match(/filename="?([^";]+)"?/i);
      const filename = m ? m[1] : `transcript.${format === "pdf" ? "pdf" : format === "docx" ? "docx" : "odt"}`;
      const url = URL.createObjectURL(blob);
      const a = document.createElement("a");
      a.href = url;
      a.download = filename;
      document.body.appendChild(a);
      a.click();
      a.remove();
      setTimeout(() => URL.revokeObjectURL(url), 1500);
    } catch (err) {
      setExportError(err?.message || String(err));
    } finally {
      setExporting(null);
    }
  }, [exporting, entries, targetLang, outputLangLabel, sessionId]);

  return (
    <section className="transcript-panel" ref={ref}>
      <div className="tx-export-bar">
        <button
          className="btn"
          onClick={() => handleExport("pdf")}
          disabled={!entries.length || !!exporting}
          title="Download transcript as PDF"
        >
          {exporting === "pdf" ? "…" : "PDF"}
        </button>
        <button
          className="btn"
          onClick={() => handleExport("docx")}
          disabled={!entries.length || !!exporting}
          title="Download transcript as Word (.docx)"
        >
          {exporting === "docx" ? "…" : "Word"}
        </button>
        <button
          className="btn"
          onClick={() => handleExport("odt")}
          disabled={!entries.length || !!exporting}
          title="Download transcript as OpenDocument (.odt)"
        >
          {exporting === "odt" ? "…" : "ODT"}
        </button>
        <div style={{flex: 1}}></div>
        {typeof perceivedMs === "number" && (
          <span
            style={{fontSize: 11, color: "var(--text-2)", alignSelf: "center", marginRight: 10, fontVariantNumeric: "tabular-nums"}}
            title="Real perceived speak→hear latency: time from end of speech to translated audio starting to play (latest utterance)"
          >
            speak→hear <b>{(perceivedMs / 1000).toFixed(1)}s</b>
          </span>
        )}
        <span style={{fontSize: 11, color: "var(--text-3)", alignSelf: "center"}}>
          {entries.length} phrase{entries.length === 1 ? "" : "s"} · target {outputLangLabel || targetLang}
        </span>
      </div>
      {exportError && (
        <div className="stream-line-error" style={{padding: "6px 10px"}}>
          Export failed — {exportError}
        </div>
      )}
      {entries.length === 0 ? (
        <div className="stream-empty" style={{padding: 24}}>
          <div>
            <div className="pict">...</div>
            <div>Transcribed phrases will appear here.</div>
          </div>
        </div>
      ) : entries.map(e => (
        <article className="tx-entry" key={e.id} data-segment-id={e.segmentId || undefined}>
          <div className="tx-head">
            <span className="tx-ts">[{fmtTs(e.ts)}]</span>
            {e.speakerLabel && (
              <span className="tx-speaker">
                {e.speakerLabel}{e.speakerRole ? ` · ${e.speakerRole}` : ""}
              </span>
            )}
          </div>
          {e.source && <div className="tx-source">{e.source}</div>}
          <div className={`tx-translated ${e.translatedState !== "done" ? "pending" : ""}`}>
            {e.translated || (e.translatedState === "pending" ? "…" : "")}
          </div>
        </article>
      ))}
    </section>
  );
}

function TranscriptPanelLegacy({ chunks, serverSessionId, targetLang, onCorrectSegment }) {
  const ref = useRef(null);
  const [editingSegmentId, setEditingSegmentId] = useState(null);
  const [drafts, setDrafts] = useState({});
  const [savingSegmentId, setSavingSegmentId] = useState(null);
  const [correctionError, setCorrectionError] = useState(null);
  // Auto-scroll to the latest transcript line as new chunks arrive.
  useEffect(() => {
    if (ref.current) ref.current.scrollTop = ref.current.scrollHeight;
  }, [chunks.length]);

  // This panel shows ONLY the SOURCE-language transcript — what the speaker
  // actually said, in the script they said it in. Translated/target-language
  // text belongs in the per-language Stream columns; do not mirror it here.
  // Render as running paragraphs (not one-line-per-utterance), with breaks
  // inserted on long silences, sentence-final punctuation, or language flips.
  const transcribedChunks = chunks.filter(c => c.sourceText);
  const paragraphs = useMemo(() => {
    const out = [];
    let current = [];
    let prev = null;
    const flush = () => {
      if (current.length) {
        out.push(current);
        current = [];
      }
    };
    const endsSentence = (t) => /[.!?。…|॥]\s*$/.test(String(t || "").trim());
    for (const c of transcribedChunks) {
      const text = String(c.sourceText || "").trim();
      if (!text) continue;
      if (prev) {
        const prevEnd = (prev.startSec || 0) + (prev.durationSec || 0);
        const gap = (c.startSec || 0) - prevEnd;
        const langFlip = prev.detectedSourceLang && c.detectedSourceLang &&
          prev.detectedSourceLang !== c.detectedSourceLang;
        if (langFlip || gap >= 2.5 || (gap >= 1.2 && endsSentence(prev.sourceText))) {
          flush();
        }
      }
      current.push({
        id: c.id,
        text,
        startSec: c.startSec,
        durationSec: c.durationSec,
        audioStorage: c.audioStorage || null,
        segmentId: c.audioStorage?.segment_id || null,
        qaState: c.qaState || null,
        correctedText: c.correctedText || null,
      });
      prev = c;
    }
    flush();
    return out;
  }, [transcribedChunks]);
  const lastStorage = (() => {
    for (let i = chunks.length - 1; i >= 0; i--) {
      const s = chunks[i] && chunks[i].audioStorage;
      if (s && s.path) return s;
    }
    return null;
  })();
  const activeStoredChunks = chunks.filter(c => c.audioStorage?.path).length;
  const prunedAudioChunks = chunks.filter(c => c.audioStorage?.pruned).length;

  // Compose a "Spoken (hi-IN)" badge from the most-recent chunk's detected
  // language so the user can tell at a glance which script they're looking
  // at — especially helpful with code-mix audio where Sarvam STT may flip
  // between hi-IN and en-IN inside one session.
  const langCatalogue = (typeof window !== "undefined" && window.SARVAM_LANGS) ? window.SARVAM_LANGS : [];
  const detectedLangCode = (() => {
    for (let i = transcribedChunks.length - 1; i >= 0; i--) {
      const code = transcribedChunks[i] && transcribedChunks[i].detectedSourceLang;
      if (code) return code;
    }
    return null;
  })();
  const detectedLangLabel = (() => {
    if (!detectedLangCode) return null;
    const meta = langCatalogue.find(l => l.code === detectedLangCode);
    return meta ? `${meta.latn} (${detectedLangCode})` : detectedLangCode;
  })();

  const fmtTime = (sec) => {
    if (sec == null) return "0:00";
    const m = Math.floor(sec / 60);
    const s = Math.floor(sec % 60);
    return `${m}:${String(s).padStart(2, "0")}`;
  };
  const fmtBytes = (n) => {
    if (!n) return "—";
    if (n < 1024) return `${n} B`;
    if (n < 1024 * 1024) return `${(n / 1024).toFixed(1)} KB`;
    return `${(n / 1024 / 1024).toFixed(2)} MB`;
  };
  const segmentTimeLabel = (seg) => {
    const startMs = Number.isFinite(seg.audioStorage?.start_ms) ? seg.audioStorage.start_ms : Math.round((seg.startSec || 0) * 1000);
    const endMs = Number.isFinite(seg.audioStorage?.end_ms)
      ? seg.audioStorage.end_ms
      : startMs + Math.round((seg.durationSec || 0) * 1000);
    return `${fmtTime(startMs / 1000)}-${fmtTime(endMs / 1000)}`;
  };
  const segmentAnchorId = (seg) => seg.segmentId ? `s2s-segment-${seg.segmentId}` : undefined;
  const csrfToken = () => document.querySelector('meta[name="csrf-token"]')?.getAttribute("content") || "";
  const beginCorrection = (seg) => {
    if (!seg.segmentId) return;
    setCorrectionError(null);
    setEditingSegmentId(seg.segmentId);
    setDrafts(prev => ({ ...prev, [seg.segmentId]: prev[seg.segmentId] ?? seg.text }));
  };
  const saveCorrection = async (seg) => {
    if (!seg.segmentId || savingSegmentId) return;
    const correctedText = String(drafts[seg.segmentId] ?? seg.text ?? "").trim();
    if (!correctedText) {
      setCorrectionError("Correction text cannot be empty.");
      return;
    }
    setSavingSegmentId(seg.segmentId);
    setCorrectionError(null);
    try {
      const res = await fetch(`/speech-to-speech/segments/${seg.segmentId}/correction`, {
        method: "POST",
        headers: {
          "Accept": "application/json",
          "Content-Type": "application/json",
          "X-Requested-With": "XMLHttpRequest",
          "X-CSRF-TOKEN": csrfToken(),
        },
        body: JSON.stringify({ corrected_text: correctedText }),
      });
      const payload = (res.headers.get("content-type") || "").includes("application/json")
        ? await res.json()
        : null;
      if (!res.ok) throw new Error(payload?.message || `Correction failed (${res.status})`);
      setEditingSegmentId(null);
      onCorrectSegment && onCorrectSegment(seg.segmentId, payload?.segment?.approved_transcript || correctedText, payload?.segment?.qa_state || "corrected");
    } catch (e) {
      setCorrectionError(e.message || String(e));
    } finally {
      setSavingSegmentId(null);
    }
  };

  return (
    <section style={{
      marginTop: 16, padding: 14, borderRadius: 10,
      border: "1px solid rgba(255,255,255,0.08)",
      background: "rgba(15,15,22,0.55)",
    }}>
      <div style={{display: "flex", alignItems: "baseline", justifyContent: "space-between", gap: 12, marginBottom: 10}}>
        <div style={{display: "flex", alignItems: "baseline", gap: 10}}>
          <h3 style={{margin: 0, fontFamily: "var(--f-display)", fontSize: 13, letterSpacing: "0.12em", textTransform: "uppercase", color: "var(--text-2)"}}>
            Live transcription
          </h3>
          {detectedLangLabel && (
            <span
              title="Source language detected by Sarvam STT — this panel shows what was spoken, not the translation"
              style={{
                fontFamily: "var(--f-mono)", fontSize: 10, letterSpacing: "0.08em",
                padding: "2px 7px", borderRadius: 999,
                background: "rgba(255,138,31,0.16)", color: "var(--saffron)",
                textTransform: "none",
              }}
            >
              Spoken · {detectedLangLabel}
            </span>
          )}
        </div>
        <div style={{display: "flex", gap: 6}}>
          <a
            href={serverSessionId ? `/speech-to-speech/sessions/${serverSessionId}/transcript.txt` : "#"}
            onClick={(e) => { if (!serverSessionId) e.preventDefault(); }}
            target="_blank" rel="noreferrer"
            style={{
              padding: "4px 10px", borderRadius: 6, fontSize: 11, fontFamily: "var(--f-mono)",
              background: serverSessionId ? "rgba(30,199,138,0.18)" : "rgba(255,255,255,0.05)",
              color: serverSessionId ? "#1ec78a" : "rgba(255,255,255,0.4)",
              textDecoration: "none", cursor: serverSessionId ? "pointer" : "not-allowed",
            }}
            title={serverSessionId ? "Download as plain text" : "Start a session first"}
          >⇩ .txt</a>
          <a
            href={serverSessionId ? `/speech-to-speech/sessions/${serverSessionId}/transcript.srt` : "#"}
            onClick={(e) => { if (!serverSessionId) e.preventDefault(); }}
            target="_blank" rel="noreferrer"
            style={{
              padding: "4px 10px", borderRadius: 6, fontSize: 11, fontFamily: "var(--f-mono)",
              background: serverSessionId ? "rgba(122,108,255,0.18)" : "rgba(255,255,255,0.05)",
              color: serverSessionId ? "#a99dff" : "rgba(255,255,255,0.4)",
              textDecoration: "none", cursor: serverSessionId ? "pointer" : "not-allowed",
            }}
            title={serverSessionId ? "Download as SubRip subtitles" : "Start a session first"}
          >⇩ .srt</a>
          <a
            href={serverSessionId ? `/speech-to-speech/sessions/${serverSessionId}/transcript.json` : "#"}
            onClick={(e) => { if (!serverSessionId) e.preventDefault(); }}
            target="_blank" rel="noreferrer"
            style={{
              padding: "4px 10px", borderRadius: 6, fontSize: 11, fontFamily: "var(--f-mono)",
              background: serverSessionId ? "rgba(255,138,31,0.18)" : "rgba(255,255,255,0.05)",
              color: serverSessionId ? "var(--saffron)" : "rgba(255,255,255,0.4)",
              textDecoration: "none", cursor: serverSessionId ? "pointer" : "not-allowed",
            }}
            title={serverSessionId ? "Download structured transcript with timestamps and audio links" : "Start a session first"}
          >⇩ .json</a>
        </div>
      </div>

      <div
        ref={ref}
        style={{
          height: 220, overflowY: "auto",
          padding: "12px 14px", borderRadius: 6,
          background: "rgba(0,0,0,0.35)", color: "var(--text-1)",
          fontFamily: "var(--f-body, system-ui)", fontSize: 14, lineHeight: 1.6,
        }}
      >
        {paragraphs.length === 0 ? (
          <div style={{color: "var(--text-3)", textAlign: "center", marginTop: 70, fontStyle: "italic", fontFamily: "var(--f-mono)", fontSize: 12.5}}>
            Speak — transcribed text will appear here as a running paragraph.
          </div>
        ) : paragraphs.map((para) => (
          <div
            key={para[0].id + "-p"}
            style={{margin: "0 0 12px 0"}}
          >
            <span style={{
              color: "var(--text-3)", marginRight: 8,
              fontFamily: "var(--f-mono)", fontSize: 11,
              opacity: 0.7,
            }}>
              [{fmtTime(para[0].startSec)}]
            </span>
            {para.map((seg, idx) => (
              <React.Fragment key={`${seg.id}-text`}>
                {idx > 0 ? " " : ""}
                <span
                  id={segmentAnchorId(seg)}
                  data-segment-id={seg.segmentId || undefined}
                  data-segment-start={seg.audioStorage?.start_ms ?? Math.round((seg.startSec || 0) * 1000)}
                  data-segment-end={seg.audioStorage?.end_ms ?? Math.round(((seg.startSec || 0) + (seg.durationSec || 0)) * 1000)}
                >
                  {seg.text}
                </span>
              </React.Fragment>
            ))}
            <span style={{display: "block", marginTop: 5, fontFamily: "var(--f-mono)", fontSize: 10, color: "var(--text-3)"}}>
              {para.map(seg => {
                const url = seg.audioStorage?.download_url;
                const label = segmentTimeLabel(seg);
                return url ? (
                  <a
                    key={seg.id}
                    href={url}
                    target="_blank"
                    rel="noreferrer"
                    style={{color: "var(--saffron)", textDecoration: "none", marginRight: 10}}
                    title={`Source audio ${label}`}
                  >
                    {label}
                  </a>
                ) : seg.audioStorage?.pruned ? (
                  <span
                    key={seg.id}
                    style={{marginRight: 10, color: "#ffb066"}}
                    title={`Source audio pruned by ${seg.audioStorage.pruned_reason || "retention policy"}`}
                  >
                    {label} · pruned
                  </span>
                ) : (
                  <span key={seg.id} style={{marginRight: 10}}>{label}</span>
                );
              })}
            </span>
            <span style={{display: "block", marginTop: 8}}>
              {para.map(seg => {
                const canCorrect = !!seg.segmentId;
                const editing = editingSegmentId === seg.segmentId;
                const draft = drafts[seg.segmentId] ?? seg.text;
                return (
                  <span
                    key={`${seg.id}-edit`}
                    data-edit-anchor={segmentAnchorId(seg)}
                    style={{
                      display: "block",
                      marginTop: 7,
                      padding: "7px 8px",
                      borderRadius: 6,
                      background: "rgba(255,255,255,0.035)",
                      border: "1px solid rgba(255,255,255,0.06)",
                    }}
                  >
                    <span style={{
                      display: "flex",
                      alignItems: "center",
                      justifyContent: "space-between",
                      gap: 8,
                      fontFamily: "var(--f-mono)",
                      fontSize: 10,
                      color: "var(--text-3)",
                    }}>
                      <span>
                        {segmentTimeLabel(seg)}
                        {seg.qaState === "corrected" && <b style={{color: "#1ec78a", marginLeft: 8}}>corrected</b>}
                      </span>
                      <button
                        type="button"
                        disabled={!canCorrect || savingSegmentId === seg.segmentId}
                        onClick={() => editing ? saveCorrection(seg) : beginCorrection(seg)}
                        style={{
                          padding: "3px 7px",
                          borderRadius: 5,
                          border: "1px solid rgba(255,255,255,0.12)",
                          background: canCorrect ? "rgba(30,199,138,0.12)" : "rgba(255,255,255,0.04)",
                          color: canCorrect ? "#1ec78a" : "rgba(255,255,255,0.35)",
                          cursor: canCorrect ? "pointer" : "not-allowed",
                          font: "inherit",
                        }}
                        title={canCorrect ? "Save a corrected transcript for this exact audio segment" : "Segment id not available yet"}
                      >
                        {savingSegmentId === seg.segmentId ? "saving" : (editing ? "save" : "correct")}
                      </button>
                    </span>
                    {editing && (
                      <textarea
                        rows="2"
                        value={draft}
                        onChange={e => setDrafts(prev => ({ ...prev, [seg.segmentId]: e.target.value }))}
                        style={{
                          width: "100%",
                          marginTop: 7,
                          boxSizing: "border-box",
                          resize: "vertical",
                          minHeight: 54,
                          borderRadius: 5,
                          border: "1px solid rgba(255,255,255,0.14)",
                          background: "rgba(0,0,0,0.35)",
                          color: "var(--text-1)",
                          fontFamily: "var(--f-body, system-ui)",
                          fontSize: 13,
                          lineHeight: 1.45,
                          padding: "7px 8px",
                        }}
                      />
                    )}
                  </span>
                );
              })}
            </span>
          </div>
        ))}
      </div>

      {correctionError && (
        <div style={{marginTop: 8, color: "#ff8a8a", fontFamily: "var(--f-mono)", fontSize: 11}}>
          {correctionError}
        </div>
      )}

      <div style={{
        marginTop: 10, padding: 8,
        background: "rgba(0,0,0,0.25)", borderRadius: 6,
        fontFamily: "var(--f-mono)", fontSize: 10.5, color: "var(--text-3)",
        display: "flex", gap: 14, flexWrap: "wrap",
      }}>
        <span><b style={{color: "var(--text-2)"}}>Input audio store</b></span>
        <span>disk: <span style={{color: "var(--text-1)"}}>{lastStorage?.disk || "—"}</span></span>
        <span>last path: <span style={{color: "var(--text-1)", wordBreak: "break-all"}}>{lastStorage?.path || "—"}</span></span>
        <span>size: <span style={{color: "var(--text-1)"}}>{fmtBytes(lastStorage?.size)}</span></span>
        <span>audio active: <span style={{color: "var(--text-1)"}}>{activeStoredChunks}</span></span>
        <span>audio pruned: <span style={{color: "var(--text-1)"}}>{prunedAudioChunks}</span></span>
      </div>
    </section>
  );
}

Object.assign(window, { Icon, TopBar, DbMeter, SettingsModal, ProviderHealth, BenchmarkPanel, AnalogClock, TranscriptPanel, SpeakerPicker, SPEAKER_ROSTER, MobileNavToggle, MobileNavTogglePortal });
