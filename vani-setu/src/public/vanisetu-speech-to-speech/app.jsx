// app.jsx — Vanisetu main app
// Composes recording + transcription + multi-language streams + insertions.

const { useState, useEffect, useRef, useCallback, useMemo } = React;

const TWEAK_DEFAULTS = /*EDITMODE-BEGIN*/{
  "accent": "saffron",
  "density": "regular",
  "showTelemetry": true,
  "showInserts": true,
  "waveStyle": "ribbon"
}/*EDITMODE-END*/;

// Accent palette options exposed via Tweaks
const ACCENTS = {
  saffron: { primary: "#ff8a1f", deep: "#d96812", glow: "rgba(255,138,31,0.35)" },
  indigo:  { primary: "#7a6cff", deep: "#5443d6", glow: "rgba(122,108,255,0.35)" },
  emerald: { primary: "#1ec78a", deep: "#0d8a5d", glow: "rgba(30,199,138,0.35)" },
  rose:    { primary: "#ff5d8a", deep: "#d63867", glow: "rgba(255,93,138,0.35)" },
};

// Phrase window tuned to typical Hindi/English sentence length (4-5s)
// so each chunk ASR Sarvam sees usually contains a complete sentence.
// Operators can still nudge it via the slider for slow/fast speakers.
const LIVE_CHUNK_DEFAULT_SECONDS = 4.5;
const LIVE_CHUNK_MIN_SECONDS = 2;
const LIVE_CHUNK_MAX_SECONDS = 12;
const LIVE_CHUNK_STEP_SECONDS = 0.5;
const AUDIBLE_FALLBACK_LANG = "hi-IN";

function s2sLanguageMeta(code) {
  return (SARVAM_LANGS || []).find(l => l.code === code) || null;
}

function s2sLanguageLabel(code) {
  return s2sLanguageMeta(code)?.latn || code;
}

function hasSarvamAudioOutput(code) {
  return !!s2sLanguageMeta(code)?.audio;
}

function audibleFallbackLangFor(code) {
  return code && !hasSarvamAudioOutput(code) ? AUDIBLE_FALLBACK_LANG : null;
}

function formatStreamTime(sec) {
  const safe = Number.isFinite(sec) ? Math.max(0, sec) : 0;
  const m = Math.floor(safe / 60);
  const s = Math.floor(safe % 60);
  return `${m}:${String(s).padStart(2, "0")}`;
}

function LiveOutputStreams({ streamsByLang, langLatency, langPerceived = {} }) {
  return (
    <section className="streams live-output-streams" aria-label="Live translated output streams">
      {streamsByLang.map(stream => {
        const meta = s2sLanguageMeta(stream.code);
        const visibleItems = stream.items.filter(item => item.type === "insert" || item.text || item.state === "pending" || item.state === "error");
        const avgMs = langLatency[stream.code];
        const perceivedMs = langPerceived[stream.code];
        return (
          <article className="stream" key={stream.code} data-output-language={stream.code}>
            <header className="stream-head">
              <div className="name">
                <span className="deva">{meta?.deva || stream.code}</span>
                <span className="latn">{meta?.latn || stream.code}</span>
              </div>
              <div className="meta">
                <span title="Real perceived speak→hear latency: time from end of speech to translated audio starting to play (latest utterance)">
                  speak→hear <span className="v">{typeof perceivedMs === "number" ? `${(perceivedMs / 1000).toFixed(1)}s` : "-"}</span>
                </span>
                <span title="Average server first-byte receive latency">avg <span className="v">{avgMs ? `${Math.round(avgMs)}ms` : "-"}</span></span>
                <span>{meta?.audio ? "audio" : "text"}</span>
              </div>
            </header>
            <div className="stream-body">
              {visibleItems.length === 0 ? (
                <div className="stream-empty">
                  <div>
                    <div className="pict">...</div>
                    <div>Translated output appears here.</div>
                  </div>
                </div>
              ) : visibleItems.map((item, idx) => {
                if (item.type === "insert") {
                  return (
                    <div className={`tx-insert ${item.kind || "note"}`} key={`ins-${item.id || idx}`}>
                      <span className="lbl">{item.label || item.kind || "insert"}</span>
                      {item.desc && <span className="desc">{item.desc}</span>}
                      {item.text && <div className="text">{item.text}</div>}
                    </div>
                  );
                }
                const locator = item.outputLocator || {};
                const state = item.state || "pending";
                const hasAudio = !!item.audioUrl || !!locator.translated_audio_url;
                const playback = item.playbackState ? ` · ${item.playbackState}` : "";
                return (
                  <div
                    className={`tx-chunk ${state !== "done" ? "partial" : ""}`}
                    key={`chunk-${stream.code}-${item.chunkId}`}
                    data-output-segment-id={locator.segment_id || undefined}
                    data-output-language={locator.language_code || stream.code}
                    data-output-start={locator.start_ms ?? undefined}
                    data-output-end={locator.end_ms ?? undefined}
                    data-output-audio-resign-url={locator.audio_resign_url || undefined}
                    data-output-source-anchor={locator.source_replay_anchor || undefined}
                  >
                    <span className="ts">{formatStreamTime(item.startSec)}</span>
                    <span className="text">
                      <span className="deva-line">{item.text || (state === "pending" ? "..." : "")}</span>
                      <span className="stream-line-meta">
                        {state}{hasAudio ? " · audio linked" : ""}{playback}{locator.segment_id ? ` · segment ${locator.segment_id}` : ""}
                      </span>
                      {item.playbackError && <span className="stream-line-error">{item.playbackError}</span>}
                      {item.error && <span className="stream-line-error">{item.error}</span>}
                    </span>
                  </div>
                );
              })}
            </div>
          </article>
        );
      })}
    </section>
  );
}

function App() {
  const [t, setTweak] = useTweaks(TWEAK_DEFAULTS);

  // ── state ──────────────────────────────────────────────────
  // Iter 16e (2026-05-27): browser-key UI removed from SettingsModal; the live
  // pipeline (Laravel → ml-gateway → Sarvam) holds the key server-side, so the
  // client-side `apiKey` state + localStorage sync are now dead and removed.
  const [settingsOpen, setSettingsOpen] = useState(false);
  const [vocabularyOpen, setVocabularyOpen] = useState(false);
  // Iter 15.5 (2026-05-27): one-time discoverability banner advertising the
  // Space-to-record shortcut. Dismissal persists in localStorage so returning
  // operators don't see it again. Iter 16a fix: hydrate synchronously in the
  // useState initialiser so first-time users see the banner (storage miss →
  // false → banner visible) without a flash, and dismissed users never see it.
  const [kbdHintDismissed, setKbdHintDismissed] = useState(
    () => typeof localStorage !== "undefined" && localStorage.getItem("s2s_kbd_hint_dismissed") === "1"
  );
  const dismissKbdHint = () => {
    localStorage.setItem("s2s_kbd_hint_dismissed", "1");
    setKbdHintDismissed(true);
  };
  // Phrase length persists across reloads so an operator doesn't lose
  // their tuned-for-speaker setting on a refresh. localStorage is the
  // right home because the value is per-device (different mics / room
  // acoustics) rather than per-account.
  const [chunkSeconds, setChunkSeconds] = useState(() => {
    try {
      const raw = localStorage.getItem("vanisetu.chunkSeconds");
      const parsed = raw == null ? NaN : parseFloat(raw);
      if (Number.isFinite(parsed)
          && parsed >= LIVE_CHUNK_MIN_SECONDS
          && parsed <= LIVE_CHUNK_MAX_SECONDS) {
        return parsed;
      }
    } catch (_) { /* localStorage may be locked */ }
    return LIVE_CHUNK_DEFAULT_SECONDS;
  });
  useEffect(() => {
    try { localStorage.setItem("vanisetu.chunkSeconds", String(chunkSeconds)); }
    catch (_) { /* noop */ }
  }, [chunkSeconds]);

  // VAD endpointing (Iter 21, 2026-06-01). When on, a chunk is emitted as
  // soon as the speaker pauses instead of waiting the full `chunkSeconds`
  // window — `chunkSeconds` then acts as a hard max cap. This is the single
  // biggest speak→hear latency win, and ending chunks on a pause also feeds
  // the translator whole clauses instead of mid-word cuts. Per-device
  // setting (mic/room acoustics differ), so it lives in localStorage.
  const [vadEnabled, setVadEnabled] = useState(
    () => { try { return localStorage.getItem("vanisetu.vadEnabled") !== "0"; } catch (_) { return true; } }
  );
  const [vadSilenceMs, setVadSilenceMs] = useState(
    () => { try { const n = parseInt(localStorage.getItem("vanisetu.vadSilenceMs"), 10); return Number.isFinite(n) ? n : 600; } catch (_) { return 600; } }
  );
  useEffect(() => {
    try {
      localStorage.setItem("vanisetu.vadEnabled", vadEnabled ? "1" : "0");
      localStorage.setItem("vanisetu.vadSilenceMs", String(vadSilenceMs));
    } catch (_) { /* noop */ }
  }, [vadEnabled, vadSilenceMs]);
  // Stable object identity so useRecorder's effect only re-runs on real change.
  const vadConfig = useMemo(
    () => ({ enabled: vadEnabled, silenceMs: vadSilenceMs }),
    [vadEnabled, vadSilenceMs]
  );

  // One-click latency presets (Iter 21): trade speak→hear speed against
  // phrase completeness in a single move. Conversation = snappiest (short
  // pause, tight cap); Accuracy = fuller clauses for the translator (longer
  // pause, higher cap); Balanced is the default. Set before recording —
  // chunkSeconds (the max cap) is read at start().
  const LATENCY_PRESETS = {
    conversation: { vadEnabled: true, vadSilenceMs: 400, chunkSeconds: 4 },
    balanced:     { vadEnabled: true, vadSilenceMs: 600, chunkSeconds: 6 },
    accuracy:     { vadEnabled: true, vadSilenceMs: 900, chunkSeconds: 9 },
  };
  const applyLatencyPreset = useCallback((name) => {
    const p = LATENCY_PRESETS[name];
    if (!p) return;
    setVadEnabled(p.vadEnabled);
    setVadSilenceMs(p.vadSilenceMs);
    setChunkSeconds(Math.min(LIVE_CHUNK_MAX_SECONDS, Math.max(LIVE_CHUNK_MIN_SECONDS, p.chunkSeconds)));
  }, []);

  // Source language is fixed to auto-detect — the operator-facing UI no
  // longer exposes ASR model selection. Saaras v3 + codemix is wired as
  // the Laravel/ml-gateway default; flipping it requires a config change
  // rather than a per-session toggle.
  const sourceLang = "auto";
  const [outputLang, setOutputLang] = useState(() => {
    try {
      const stored = localStorage.getItem("vanisetu.outputLang");
      if (stored && (SARVAM_LANGS || []).some(l => l.code === stored && l.audio)) {
        return stored;
      }
    } catch (_) { /* noop */ }
    return "hi-IN";
  });
  useEffect(() => {
    try { localStorage.setItem("vanisetu.outputLang", outputLang); }
    catch (_) { /* noop */ }
  }, [outputLang]);
  const audibleFallbackLang = useMemo(() => audibleFallbackLangFor(outputLang), [outputLang]);
  // Phase 3 (feature-flagged): multi-language output. When on, `extraLangs`
  // render as additional text panels alongside the primary (audio) language.
  // Default off -> exact single-select behaviour preserved.
  const [multiLang, setMultiLang] = useState(() => {
    try { return localStorage.getItem("vanisetu.multiLang") === "1"; } catch (_) { return false; }
  });
  useEffect(() => { try { localStorage.setItem("vanisetu.multiLang", multiLang ? "1" : "0"); } catch (_) { /* noop */ } }, [multiLang]);
  const [extraLangs, setExtraLangs] = useState(() => {
    try { const v = JSON.parse(localStorage.getItem("vanisetu.extraLangs") || "[]"); return Array.isArray(v) ? v : []; } catch (_) { return []; }
  });
  useEffect(() => { try { localStorage.setItem("vanisetu.extraLangs", JSON.stringify(extraLangs)); } catch (_) { /* noop */ } }, [extraLangs]);
  const [voice, setVoice] = useState(() => {
    const stored = localStorage.getItem("vanisetu.voice");
    // Self-heal: if the cached voice isn't in the verified Bulbul v3 roster
    // (e.g. left over from an earlier default like "anushka" — which v3
    // returns HTTP 400 for and produces silence), drop back to the default.
    const valid = (SARVAM_VOICES || []).some(v => v.id === stored);
    return valid ? stored : "ritu";
  });
  const targets = useMemo(() => {
    const list = [outputLang];
    if (multiLang) { for (const c of extraLangs) { if (c && c !== outputLang) list.push(c); } }
    if (audibleFallbackLang && audibleFallbackLang !== outputLang) list.push(audibleFallbackLang);
    return [...new Set(list)];
  }, [outputLang, audibleFallbackLang, multiLang, extraLangs]);

  useEffect(() => {
    if (voice) localStorage.setItem("vanisetu.voice", voice);
  }, [voice]);

  const [inputGain, setInputGain] = useState(0.9);
  const [outputVolume, setOutputVolume] = useState(0.9);
  const [outputDevices, setOutputDevices] = useState([]);
  const [outputDeviceId, setOutputDeviceId] = useState("");

  // chunks: array of { id, startSec, durationSec, state, sendMs, recvMs, totalMs, perLang: {code: {text, sendMs, recvMs, state}} }
  const [chunks, setChunks] = useState([]);
  // inserts: { id, atChunkId (after which chunk), atSec, kind, label/desc/text }
  const [inserts, setInserts] = useState([]);

  // session timing
  const [sessionMs, setSessionMs] = useState(0);
  const sessionStartRef = useRef(null);
  const tickRef = useRef(null);
  const [paused, setPaused] = useState(false);
  const playbackChainRef = useRef(Promise.resolve());
  const playbackTokenRef = useRef(0);
  const currentAudioRef = useRef(null);
  const currentAudioResolveRef = useRef(null);
  const queuedAudioRef = useRef(new Set());
  // Streaming-mode bookkeeping: every in-flight SSE fetch registers an
  // AbortController here, and every per-language sentence queue registers
  // itself so stopOutput() can silence both the network reader and the
  // audio queue that's already buffered sentences into memory.
  const streamingAbortersRef = useRef(new Set());
  const streamingQueuesRef = useRef(new Set());

  const cleanOutputText = useCallback((text) => (
    String(text || "").replace(/^\s*\[IndicTrans2(?:\s+deterministic)?(?:\s+draft)?(?:\s+[a-z]{2}(?:-[A-Z]{2})?)?\]\s*/i, "").trim()
  ), []);
  const sameLanguage = useCallback((a, b) => {
    if (!a || !b || a === "auto" || b === "auto") return false;
    return String(a).toLowerCase().split("-")[0] === String(b).toLowerCase().split("-")[0];
  }, []);
  const inferSourceLanguage = useCallback((lang, text) => {
    const normalized = String(lang || "").toLowerCase();
    if (normalized.startsWith("hi")) return "hi-IN";
    if (normalized.startsWith("en")) return "en-IN";
    if (normalized.startsWith("bn")) return "bn-IN";
    if (normalized.startsWith("ta")) return "ta-IN";
    if (normalized.startsWith("te")) return "te-IN";
    if (normalized.startsWith("kn")) return "kn-IN";
    if (normalized.startsWith("ml")) return "ml-IN";
    if (normalized.startsWith("mr")) return "mr-IN";
    if (normalized.startsWith("gu")) return "gu-IN";
    if (normalized.startsWith("pa")) return "pa-IN";
    if (normalized.startsWith("od") || normalized.startsWith("or")) return "od-IN";
    const value = String(text || "");
    if (/[\u0900-\u097F]/.test(value)) return "hi-IN";
    if (/[\u0980-\u09FF]/.test(value)) return "bn-IN";
    if (/[\u0B80-\u0BFF]/.test(value)) return "ta-IN";
    if (/[\u0C00-\u0C7F]/.test(value)) return "te-IN";
    if (/[\u0C80-\u0CFF]/.test(value)) return "kn-IN";
    if (/[\u0D00-\u0D7F]/.test(value)) return "ml-IN";
    if (/[\u0A80-\u0AFF]/.test(value)) return "gu-IN";
    if (/[\u0A00-\u0A7F]/.test(value)) return "pa-IN";
    if (/[\u0B00-\u0B7F]/.test(value)) return "od-IN";
    return "en-IN";
  }, []);

  const refreshOutputDevices = useCallback(async () => {
    if (!navigator.mediaDevices?.enumerateDevices) return;
    try {
      const all = await navigator.mediaDevices.enumerateDevices();
      setOutputDevices(all.filter(d => d.kind === "audiooutput"));
    } catch (e) {
      setOutputDevices([]);
    }
  }, []);

  useEffect(() => {
    refreshOutputDevices();
    navigator.mediaDevices?.addEventListener?.("devicechange", refreshOutputDevices);
    return () => navigator.mediaDevices?.removeEventListener?.("devicechange", refreshOutputDevices);
  }, [refreshOutputDevices]);

  // ── chunk lifecycle ────────────────────────────────────────
  const updateChunk = useCallback((id, patch) => {
    setChunks(prev => prev.map(c => c.id === id ? { ...c, ...patch } : c));
  }, []);
  const inputDeviceIdRef = useRef(null);

  const handleChunk = useCallback(async ({ id, wav, startSec, audioStartSec, durationSec, overlapSec, sampleRate, final }) => {
    // immediately push placeholder chunk in "uploading" state
    setChunks(prev => [...prev, {
      id, startSec, durationSec, sampleRate,
      state: "uploading",
      perLang: Object.fromEntries(targets.map(l => [l, { text: "", state: "pending" }])),
      desiredOutputLang: outputLang,
      audibleFallbackLang,
      sendMs: null, recvMs: null, totalMs: null,
      final: !!final,
    }]);

    const demo = false;
    const t0 = performance.now();
    // Each chunk gets its own AbortController so a Stop press cancels the
    // in-flight SSE/HTTP request but doesn't interfere with later chunks
    // started after the user hits Record again.
    const aborter = new AbortController();
    streamingAbortersRef.current.add(aborter);
    const registerQueue = (q) => { streamingQueuesRef.current.add(q); };
    try {
      const { stt, results } = await processChunk({
        chunkIdx: id,
        wav,
        startSec,
        audioStartSec,
        durationSec,
        overlapSec,
        sourceLang,
        targetLangs: targets,
        voice,
        // Operator UI no longer exposes ASR knobs; the Laravel pipeline
        // applies the saaras:v3 + codemix defaults from S2sConfigRepository
        // so we send nothing here and the server-side config wins.
        deviceId: inputDeviceIdRef.current,
        outputDeviceId,
        outputVolume,
        demo,
        signal: aborter.signal,
        registerQueue,
        onPartial: (p) => {
          // update one language slot as soon as we have it
          setChunks(prev => prev.map(c => {
            if (c.id !== id) return c;
            const perLang = { ...c.perLang };
            if (p.kind === "stt") {
              const sourceText = cleanOutputText(p.text);
              const detected = inferSourceLanguage(p.lang || sourceLang, sourceText);
              const detectedTarget = Object.keys(perLang).find(lang => sameLanguage(lang, detected));
              if (detectedTarget) {
                perLang[detectedTarget] = { text: "", state: "skipped_source", sendMs: p.sendMs, recvMs: p.recvMs };
              }
              return {
                ...c,
                detectedSourceLang: detected,
                desiredOutputLang: outputLang,
                audibleFallbackLang,
                sourceText,
                rawSourceText: p.rawText || sourceText,
                qaState: p.qaState || c.qaState || null,
                correctedText: p.correctedText || c.correctedText || null,
                perLang,
                audioStorage: p.audioStorage || null,
                ts: Date.now()
              };
            } else if (p.kind === "archive") {
              return { ...c, audioStorage: p.audioStorage || null, ts: Date.now() };
            } else if (p.kind === "translate") {
              const detected = inferSourceLanguage(c.detectedSourceLang || sourceLang, c.sourceText);
              const desiredOutput = c.desiredOutputLang || outputLang;
              const fallbackOutput = c.audibleFallbackLang || audibleFallbackLang;
              const isAudibleFallback = fallbackOutput &&
                sameLanguage(p.lang, fallbackOutput) &&
                !sameLanguage(p.lang, desiredOutput);
              const text = cleanOutputText(p.text);
              if ((sameLanguage(detected, desiredOutput) || !sameLanguage(p.lang, desiredOutput)) && !isAudibleFallback) {
                perLang[p.lang] = { text: "", audioUrl: null, state: "skipped_source", sendMs: p.sendMs, recvMs: p.recvMs };
              } else {
                perLang[p.lang] = {
                  text,
                  audioUrl: p.audioUrl || null,
                  outputId: p.outputId || null,
                  outputLocator: p.outputLocator || null,
                  state: p.degraded ? "degraded" : "done",
                  degraded: !!p.degraded,
                  audibleFallback: !!isAudibleFallback,
                  fallbackFor: isAudibleFallback ? desiredOutput : null,
                  sendMs: p.sendMs,
                  recvMs: p.recvMs
                };
                if (p.audioUrl) enqueueAudio(p.audioUrl, `${id}:${p.lang}`, p.outputId || null);
              }
            } else if (p.kind === "audio_locator") {
              const existing = perLang[p.lang] || {};
              perLang[p.lang] = {
                ...existing,
                audioUrl: p.audioUrl || existing.audioUrl || null,
                audioKey: p.audioKey || existing.audioKey || null,
                outputLocator: p.outputLocator || existing.outputLocator || null,
                state: existing.state === "pending" ? "done" : (existing.state || "done"),
                sendMs: p.sendMs ?? existing.sendMs,
                recvMs: p.recvMs ?? existing.recvMs
              };
            } else if (p.kind === "audio_start" || p.kind === "audio_end" || p.kind === "audio_blocked") {
              const playbackLang = p.lang || p.language_code;
              if (!playbackLang) return { ...c, perLang };
              const existing = perLang[playbackLang] || {};
              if (p.kind === "audio_blocked" && typeof window.reportS2SClientError === "function") {
                window.reportS2SClientError("audio_blocked", {
                  message: p.error || "Browser blocked streamed translated audio playback.",
                  source: "streaming_tts",
                  url: window.location.href,
                  chunk_id: id,
                  language_code: playbackLang,
                });
              }
              // Capture the REAL perceived speak->hear latency: playMs is
              // measured in sarvam.jsx as performance.now()-t0 at the moment
              // playback actually starts, where t0 is the instant the operator
              // stopped speaking. The first-audio sample (p.firstAudio) is the
              // headline number; later sentences overlap and aren't meaningful
              // as speak->hear. Previously emitted but discarded — now surfaced
              // in the per-language stream header.
              const perceivedMs = (p.kind === "audio_start" && p.firstAudio && typeof p.playMs === "number")
                ? p.playMs
                : existing.perceivedMs ?? null;
              perLang[playbackLang] = {
                ...existing,
                playbackState: p.kind === "audio_start" ? "playing" : (p.kind === "audio_end" ? "played" : "blocked"),
                playbackError: p.kind === "audio_blocked" ? (p.error || "Browser blocked audio playback.") : null,
                sentenceIndex: p.sentence_index ?? existing.sentenceIndex ?? null,
                totalSentences: p.total_sentences ?? existing.totalSentences ?? null,
                perceivedMs,
              };
            } else if (p.kind === "language_error" || p.kind === "audio_error") {
              const errorLang = p.lang || p.language_code;
              const message = p.message || p.error || `${p.kind} from streaming pipeline`;
              if (typeof window.reportS2SClientError === "function") {
                window.reportS2SClientError(p.kind, {
                  message,
                  source: "streaming_tts",
                  url: window.location.href,
                  chunk_id: id,
                  language_code: errorLang || "",
                });
              }
              if (!errorLang) return { ...c, state: "error", error: message, perLang };
              const existing = perLang[errorLang] || {};
              perLang[errorLang] = {
                ...existing,
                state: "error",
                error: message,
              };
            } else if (p.kind === "stream_error") {
              const message = p.message || p.error || "Streaming speech-to-speech pipeline failed.";
              if (typeof window.reportS2SClientError === "function") {
                window.reportS2SClientError("stream_error", {
                  message,
                  source: "streaming_tts",
                  url: window.location.href,
                  chunk_id: id,
                });
              }
              return { ...c, state: "error", error: message, perLang };
            } else if (p.kind === "error") {
              perLang[p.lang] = { text: "", state: "error", error: p.error };
            }
            return { ...c, perLang };
          }));
        },
      });
      const totalMs = performance.now() - t0;
      updateChunk(id, {
        state: "done",
        sendMs: stt.sendMs,
        recvMs: stt.recvMs,
        serverMs: stt.serverMs ?? null,
        totalMs,
      });
    } catch (e) {
      const totalMs = performance.now() - t0;
      if (e?.name === "AbortError") {
        // User pressed Stop mid-flight — keep the partial state visible
        // (whatever sourceText/translations already arrived) and just
        // mark the chunk as cancelled instead of an error red flag.
        updateChunk(id, { state: "cancelled", totalMs });
      } else {
        updateChunk(id, { state: "error", error: e.message, totalMs });
      }
    } finally {
      streamingAbortersRef.current.delete(aborter);
    }
  }, [targets, voice, outputDeviceId, outputVolume, updateChunk, cleanOutputText, sameLanguage, inferSourceLanguage, outputLang, audibleFallbackLang]);

  // Iter 14 (2026-05-27): re-sign the MinIO URL just before playback when
  // its embedded ``Expires=`` is within 60s. Recovery for long-paused
  // sessions whose stored URL would otherwise 403 at MinIO. data: URLs
  // (legacy base64 fallback) and URLs without ``Expires=`` short-circuit
  // unchanged.
  //
  // Iter 16 (2026-05-27): also handle SSE-streamed sentences that have no
  // ``outputId`` (no s2s_outputs row gets persisted on the streaming path)
  // but DO carry an ``audio_key``. Iter-15e wired ml-gateway to emit the
  // key in the SSE ``audio`` frame and added /speech-to-speech/audio-url
  // on the Laravel side to re-sign via ml-gateway /v1/audio/re-sign. We
  // prefer the outputId path when both exist (cheapest, already cached
  // on the Laravel row); fall back to audio_key for SSE-only chunks.
  const ensureFreshAudioUrl = useCallback(async (item) => {
    const url = item?.dataUrl || item?.audio_url || (typeof item === "string" ? item : null);
    if (!url || typeof url !== "string") return url;
    if (url.startsWith("data:")) return url;
    const expiresAt = presignedExpiryEpoch(url);
    if (expiresAt == null) return url;
    const remaining = expiresAt - Math.floor(Date.now() / 1000);
    if (remaining > 60) return url;
    let endpoint = null;
    if (item && item.outputId) {
      endpoint = `/speech-to-speech/outputs/${item.outputId}/audio-url`;
    } else if (item && item.audio_key) {
      endpoint = `/speech-to-speech/audio-url?key=${encodeURIComponent(item.audio_key)}`;
    }
    if (!endpoint) return url;
    try {
      const res = await fetch(endpoint, {
        headers: { "Accept": "application/json" },
      });
      if (!res.ok) return url;
      const json = await res.json();
      return json.audio_url || url;
    } catch (_) {
      return url;
    }
  }, []);

  const enqueueAudio = useCallback((url, key = url, outputId = null, audioKey = null) => {
    const queueKey = `${key}:${url}`;
    if (queuedAudioRef.current.has(queueKey)) return;
    queuedAudioRef.current.add(queueKey);
    const token = playbackTokenRef.current;
    playbackChainRef.current = playbackChainRef.current
      .catch(() => {})
      .then(() => new Promise(async (resolve) => {
        if (token !== playbackTokenRef.current) return resolve();
        // Refresh the signed URL if its TTL is about to lapse. For chunks
        // played immediately this is a near-instant no-op; the round-trip
        // only fires on resumed playback >14 min after the chunk arrived.
        const freshUrl = await ensureFreshAudioUrl({ audio_url: url, outputId, audio_key: audioKey });
        if (token !== playbackTokenRef.current) return resolve();
        const audio = new Audio(freshUrl);
        currentAudioRef.current = audio;
        currentAudioResolveRef.current = resolve;
        audio.volume = Math.max(0, Math.min(1, outputVolume));
        if (outputDeviceId && typeof audio.setSinkId === "function") {
          try { await audio.setSinkId(outputDeviceId); } catch (e) {}
        }
        const finish = () => {
          if (currentAudioRef.current === audio) currentAudioRef.current = null;
          if (currentAudioResolveRef.current === resolve) currentAudioResolveRef.current = null;
          resolve();
        };
        audio.onended = finish;
        audio.onerror = finish;
        audio.play().catch(finish);
      }));
  }, [outputVolume, outputDeviceId, ensureFreshAudioUrl]);

  useEffect(() => {
    if (currentAudioRef.current) currentAudioRef.current.volume = Math.max(0, Math.min(1, outputVolume));
  }, [outputVolume]);

  const stopOutput = useCallback(() => {
    // (a) Abort any in-flight SSE/HTTP fetch so the server stops sending
    // more sentences. Without this the network reader keeps draining and
    // enqueueing audio frames even after the user pressed Stop.
    for (const a of streamingAbortersRef.current) {
      try { a.abort(); } catch (e) { /* noop */ }
    }
    streamingAbortersRef.current.clear();

    // (b) Tear down every per-language streaming queue (used by the SSE
    // path). These hold base64-decoded audio that was already received
    // and would otherwise auto-play through the rest of the buffered
    // sentences. createSentenceAudioQueue.abort() empties the queue and
    // pauses the active HTMLAudioElement.
    for (const q of streamingQueuesRef.current) {
      try { q.abort(); } catch (e) { /* noop */ }
    }
    streamingQueuesRef.current.clear();

    // (c) Tear down the batched-mode playback chain. Bumping the token
    // invalidates the next iteration's resolve(), clearing queuedAudioRef
    // prevents new enqueueAudio() calls from being deduped-as-already-queued
    // *and* drains the to-play set.
    playbackTokenRef.current += 1;
    queuedAudioRef.current.clear();
    const audio = currentAudioRef.current;
    if (audio) {
      try { audio.pause(); } catch (e) {}
      audio.removeAttribute("src");
      try { audio.load(); } catch (e) {}
      currentAudioRef.current = null;
    }
    if (currentAudioResolveRef.current) {
      currentAudioResolveRef.current();
      currentAudioResolveRef.current = null;
    }
    playbackChainRef.current = Promise.resolve();
  }, []);

  // ── recorder ───────────────────────────────────────────────
  const { state: recState, start, stop, level, analyser, devices, deviceId, setDeviceId } = useRecorder({
    chunkSeconds,
    targetSampleRate: 16000,
    onChunk: handleChunk,
    gain: inputGain * 2, // amplify a little; gain 0..2
    vad: vadConfig,
  });
  inputDeviceIdRef.current = deviceId;
  const recording = recState === "recording";

  useEffect(() => {
    if (recording) refreshOutputDevices();
  }, [recording, refreshOutputDevices]);

  // session timer
  useEffect(() => {
    if (recording && !paused) {
      if (sessionStartRef.current == null) sessionStartRef.current = performance.now() - sessionMs;
      tickRef.current = setInterval(() => {
        setSessionMs(performance.now() - sessionStartRef.current);
      }, 53);
      return () => clearInterval(tickRef.current);
    } else {
      clearInterval(tickRef.current);
      if (!recording) sessionStartRef.current = null;
    }
  }, [recording, paused]);

  // toggle record
  //
  // When stopping the recorder we ALSO silence any audio that hasn't played
  // yet (the streaming SSE path can have several sentences queued ahead of
  // the live mic feed; without stopOutput they keep playing after the user
  // hits stop, which read as "the Stop button doesn't work"). The Laravel
  // session row is left intact — we don't call /finish — so the user can
  // resume by tapping Record again with the same backend session.
  const toggleRecord = () => {
    if (recording) {
      stop();
      stopOutput();
      setPaused(false);
    } else {
      if (chunks.length === 0 && window.resetS2SServerSession) window.resetS2SServerSession();
      queuedAudioRef.current.clear();
      setPaused(false);
      sessionStartRef.current = performance.now() - sessionMs;
      start();
    }
  };

  // Iter 16 (2026-05-27): wire the spacebar hook that was defined but
  // never invoked (iter-15d audit). The transport button label has read
  // "record · space" / "stop · space" since iter-15.5 but Space did
  // nothing because the hook never ran. The hook itself already
  // excludes INPUT/TEXTAREA/SELECT focus targets, so binding it here is
  // safe even when the settings modal is open.
  useSpacebar(toggleRecord);

  const resetSession = () => {
    if (recording) stop();
    stopOutput();
    setChunks([]);
    setInserts([]);
    setSessionMs(0);
    sessionStartRef.current = null;
    setPaused(false);
    if (window.resetS2SServerSession) window.resetS2SServerSession();
  };

  const [finishing, setFinishing] = useState(false);
  const [finishedNotice, setFinishedNotice] = useState(null);

  // Seal the current server session: tells Laravel to flip status → "finished",
  // stamp finished_at, and freeze segment/output counts in archive_meta. Audio
  // chunks and transcript segments are already tagged with sequence_no by the
  // backend on each /segments POST, so the finish call is the closing record
  // that ties them together. After it completes we drop the client-side
  // session id so the next "record" press opens a fresh session row.
  const finishSession = useCallback(async () => {
    if (finishing) return;
    const getId = window.getS2SServerSessionId;
    const sid = getId ? getId() : null;
    if (!sid) return;
    setFinishing(true);
    try {
      const csrf = document.querySelector('meta[name="csrf-token"]')?.getAttribute("content") || "";
      const res = await fetch(`/speech-to-speech/sessions/${sid}/finish`, {
        method: "POST",
        headers: {
          "Accept": "application/json",
          "X-Requested-With": "XMLHttpRequest",
          "X-CSRF-TOKEN": csrf,
        },
      });
      const payload = (res.headers.get("content-type") || "").includes("application/json")
        ? await res.json()
        : null;
      if (!res.ok) throw new Error(payload?.message || `Finish failed (${res.status})`);
      setFinishedNotice({
        sessionId: sid,
        segments: payload?.session?.segments ?? null,
        outputs: payload?.session?.outputs ?? null,
      });
      if (window.resetS2SServerSession) window.resetS2SServerSession();
    } catch (e) {
      setFinishedNotice({ error: e.message || String(e) });
    } finally {
      setFinishing(false);
    }
  }, [finishing]);

  const dismissFinishedNotice = () => setFinishedNotice(null);

  // The Finish button only makes sense once we've actually captured something
  // *and* the user has stopped recording. While recording is live the user
  // should keep streaming or stop first; once stopped, finishing seals the
  // session and starts a fresh one on the next record.
  const canFinish = !recording && !finishing && chunks.length > 0 &&
    typeof window !== "undefined" && window.getS2SServerSessionId &&
    !!window.getS2SServerSessionId();

  // ── inserts ────────────────────────────────────────────────
  const addInsert = (kind, payload = {}) => {
    const id = `i_${Date.now()}_${Math.random().toString(36).slice(2,5)}`;
    const lastChunkId = chunks.length ? chunks[chunks.length-1].id : 0;
    const atSec = sessionMs / 1000;
    setInserts(prev => [...prev, { id, kind, atChunkId: lastChunkId, atSec, ...payload }]);
  };
  const removeInsert = (id) => setInserts(prev => prev.filter(i => i.id !== id));

  const applyTranscriptCorrection = useCallback((segmentId, correctedText, qaState = "corrected") => {
    setChunks(prev => prev.map(chunk => {
      if (chunk.audioStorage?.segment_id !== segmentId) return chunk;
      return {
        ...chunk,
        sourceText: correctedText,
        correctedText,
        qaState,
        ts: Date.now(),
      };
    }));
  }, []);

  // ── derived: per-language stream items, interleaved with inserts ─
  const streamsByLang = useMemo(() => {
    return targets.map(code => {
      const items = [];
      // walk chunks, after each chunk emit any inserts that point at it
      let insertCursor = 0;
      // first, inserts BEFORE any chunk (atChunkId === 0)
      const initialInserts = inserts.filter(i => i.atChunkId === 0);
      for (const ins of initialInserts) items.push({ type: "insert", ...ins });

      for (const c of chunks) {
        const slot = c.perLang[code];
        items.push({
          type: "chunk",
          chunkId: c.id,
          startSec: c.startSec,
          text: slot ? slot.text : "",
          audioUrl: slot ? slot.audioUrl : null,
          outputLocator: slot ? slot.outputLocator : null,
          playbackState: slot ? slot.playbackState : null,
          playbackError: slot ? slot.playbackError : null,
          state: slot ? slot.state : "pending",
          error: slot ? slot.error : null,
        });
        // inserts pinned to this chunk
        const here = inserts.filter(i => i.atChunkId === c.id);
        for (const ins of here) items.push({ type: "insert", ...ins });
      }
      return { code, items };
    });
  }, [targets, chunks, inserts]);

  // ── derived: telemetry ─────────────────────────────────────
  const lastChunk = chunks.length ? chunks[chunks.length - 1] : null;
  const avg = useMemo(() => {
    const done = chunks.filter(c => c.totalMs != null);
    if (done.length === 0) return null;
    return done.reduce((s, c) => s + c.totalMs, 0) / done.length;
  }, [chunks]);
  const throughput = useMemo(() => {
    // (sum of audio durations processed) / (sum of API round-trip time)
    const done = chunks.filter(c => c.totalMs != null && c.durationSec);
    if (done.length === 0) return null;
    const audio = done.reduce((s, c) => s + c.durationSec * 1000, 0);
    const wall  = done.reduce((s, c) => s + c.totalMs, 0);
    if (wall === 0) return null;
    return audio / wall;
  }, [chunks]);

  // ── per-language avg latency (for stream header) ──────────
  const langLatency = useMemo(() => {
    const out = {};
    for (const c of chunks) {
      for (const [lang, slot] of Object.entries(c.perLang || {})) {
        if (slot.state === "done" && slot.recvMs != null) {
          if (!out[lang]) out[lang] = { sum: 0, n: 0 };
          out[lang].sum += slot.recvMs;
          out[lang].n += 1;
        }
      }
    }
    const avg = {};
    for (const [l, v] of Object.entries(out)) avg[l] = v.sum / v.n;
    return avg;
  }, [chunks]);

  // ── per-language real perceived speak->hear (latest first-audio playMs) ──
  // The headline latency number an operator cares about: how long after they
  // stopped speaking did translated audio actually start playing. Takes the
  // most recent utterance's first-audio sample per language.
  const langPerceived = useMemo(() => {
    const latest = {};
    for (const c of chunks) {
      for (const [lang, slot] of Object.entries(c.perLang || {})) {
        if (typeof slot.perceivedMs === "number") latest[lang] = slot.perceivedMs;
      }
    }
    return latest;
  }, [chunks]);

  // Derive the summary pill from ProviderHealth's existing 15s poll instead
  // of starting a second /providers/health request loop from app.jsx.
  const [apiStatus, setApiStatus] = useState({ kind: "ok", label: "Sarvam · server pipeline" });
  useEffect(() => {
    const updateFromHealth = (event) => {
      const d = event.detail || {};
      const sarvam = ["sarvam_stt", "sarvam_translate", "sarvam_tts"].map(k => d.providers?.[k]?.status);
      const masterStatus = d.providers?.master_orchestrator?.status;
      const appStatus = d.providers?.vani_setu_app?.status;
      const latencyStatus = d.providers?.s2s_latency_slo?.status;
      const clientErrorStatus = d.providers?.s2s_client_errors?.status;
      const kind = masterStatus === "down" || appStatus === "down" || sarvam.includes("down")
        ? "err"
        : masterStatus === "degraded" || appStatus === "degraded" || sarvam.includes("degraded")
          ? "warn"
          : masterStatus === "watch" || latencyStatus === "watch" || clientErrorStatus === "watch"
            ? "warn"
            : "ok";
      const label = kind === "ok"
        ? "Pipeline · live"
        : kind === "warn"
          ? (clientErrorStatus === "watch" ? "Pipeline · client watch" : (latencyStatus === "watch" ? "Pipeline · latency watch" : "Pipeline · degraded"))
          : "Pipeline · down";
      setApiStatus({ kind, label });
    };
    window.addEventListener("vani-s2s-provider-health", updateFromHealth);
    return () => window.removeEventListener("vani-s2s-provider-health", updateFromHealth);
  }, []);

  // accent live update — write CSS variables on root
  useEffect(() => {
    const a = ACCENTS[t.accent] || ACCENTS.saffron;
    const root = document.documentElement;
    root.style.setProperty("--saffron", a.primary);
    root.style.setProperty("--saffron-deep", a.deep);
    root.style.setProperty("--saffron-glow", a.glow);
  }, [t.accent]);

  const VocabularyDialogComponent = window.VocabularyDialog || null;

  return (
    <div className="shell audio-only" data-density={t.density}>
      <TopBar
        recording={recording}
        paused={paused}
        sessionMs={sessionMs}
        apiStatus={apiStatus}
        onOpenSettings={() => setSettingsOpen(true)}
        onOpenVocabulary={() => setVocabularyOpen(true)}
      />

      {!kbdHintDismissed && (
        <div style={{
          margin: "8px 16px 0",
          padding: "8px 12px",
          display: "flex",
          alignItems: "center",
          justifyContent: "space-between",
          gap: 12,
          fontFamily: "var(--f-mono)",
          fontSize: 12,
          letterSpacing: "0.04em",
          color: "var(--text-2, #ccc)",
          background: "rgba(255,138,31,0.08)",
          border: "1px solid var(--saffron-glow, rgba(255,138,31,0.35))",
          borderRadius: 6,
        }}>
          <span>
            Tip: press <kbd style={{padding:"1px 5px", border:"1px solid currentColor", borderRadius:3}}>Space</kbd> to start / stop recording.
          </span>
          <button
            onClick={dismissKbdHint}
            aria-label="Dismiss keyboard shortcut hint"
            title="Dismiss"
            style={{
              background: "transparent",
              border: "none",
              color: "inherit",
              cursor: "pointer",
              fontSize: 16,
              lineHeight: 1,
              padding: "0 4px",
            }}
          >×</button>
        </div>
      )}

      <div className="body-grid">
        {/* ── LEFT: minimal capture + output controls ─────── */}
        <aside className="side">
          {/* Input — mic + gain + persisted phrase length */}
          <section className="side-section">
            <h4>Input</h4>
            <div className="field">
              <label>Microphone</label>
              <div className="select-wrap">
                <select value={deviceId || ""} onChange={e => setDeviceId(e.target.value || null)}>
                  <option value="">System default</option>
                  {devices.map(d => (
                    <option key={d.deviceId} value={d.deviceId}>
                      {d.label || `Device ${d.deviceId.slice(0,6)}`}
                    </option>
                  ))}
                </select>
              </div>
            </div>
            <div className="field">
              <label>
                Input gain
                <span className="val">{Math.round(inputGain * 100)}%</span>
              </label>
              <input
                type="range" className="slider"
                min="0" max="2" step="0.05"
                value={inputGain}
                onChange={e => setInputGain(parseFloat(e.target.value))}
              />
            </div>
            <div className="field">
              <label>Latency preset</label>
              <div className="preset-row" style={{ display: "flex", gap: "6px" }}>
                <button type="button" className="preset-btn" onClick={() => applyLatencyPreset("conversation")}>Conversation</button>
                <button type="button" className="preset-btn" onClick={() => applyLatencyPreset("balanced")}>Balanced</button>
                <button type="button" className="preset-btn" onClick={() => applyLatencyPreset("accuracy")}>Accuracy</button>
              </div>
            </div>
            <div className="field">
              <label className="toggle-label" style={{ display: "flex", alignItems: "center", justifyContent: "space-between", cursor: "pointer" }}>
                <span>Auto-send on pause (VAD)</span>
                <input
                  type="checkbox"
                  checked={vadEnabled}
                  onChange={e => setVadEnabled(e.target.checked)}
                />
              </label>
            </div>
            {vadEnabled && (
              <div className="field">
                <label>
                  Pause to send
                  <span className="val">{vadSilenceMs} ms</span>
                </label>
                <input
                  type="range" className="slider"
                  min={300} max={1200} step={50}
                  value={vadSilenceMs}
                  onChange={e => setVadSilenceMs(parseInt(e.target.value, 10))}
                />
              </div>
            )}
            <div className="field">
              <label>
                {vadEnabled ? "Max phrase length" : "Phrase length"}
                <span className="val">{chunkSeconds.toFixed(1)}s</span>
              </label>
              <input
                type="range" className="slider"
                min={LIVE_CHUNK_MIN_SECONDS}
                max={LIVE_CHUNK_MAX_SECONDS}
                step={LIVE_CHUNK_STEP_SECONDS}
                value={chunkSeconds}
                onChange={e => setChunkSeconds(parseFloat(e.target.value))}
              />
            </div>
          </section>

          {/* Output — language, voice, device, volume */}
          <section className="side-section">
            <h4>Output</h4>
            <div className="field">
              <label>Language</label>
              <div className="select-wrap">
                <select value={outputLang} onChange={e => {
                  stopOutput();
                  setOutputLang(e.target.value);
                }}>
                  {SARVAM_LANGS.filter(l => l.audio).map(l => (
                    <option key={l.code} value={l.code}>
                      {l.latn} · {l.deva}
                    </option>
                  ))}
                </select>
              </div>
            </div>
            <div className="field">
              <label>
                <input type="checkbox" checked={multiLang} onChange={e => { stopOutput(); setMultiLang(e.target.checked); }} />
                {" "}Multi-language output
              </label>
              {multiLang && (
                <div className="multi-lang-picker" style={{ display: "flex", flexWrap: "wrap", gap: "4px 10px", marginTop: 4 }}>
                  {SARVAM_LANGS.filter(l => l.code !== outputLang).map(l => (
                    <label key={l.code} style={{ display: "inline-flex", alignItems: "center", gap: 3, fontSize: "0.85em" }}>
                      <input
                        type="checkbox"
                        checked={extraLangs.includes(l.code)}
                        onChange={e => {
                          stopOutput();
                          setExtraLangs(prev => e.target.checked ? [...new Set([...prev, l.code])] : prev.filter(c => c !== l.code));
                        }}
                      />
                      {l.latn}{l.audio ? "" : " (text)"}
                    </label>
                  ))}
                </div>
              )}
            </div>
            <div className="field">
              <label>Voice</label>
              <div className="select-wrap">
                <select value={voice} onChange={e => {
                  stopOutput();
                  setVoice(e.target.value);
                }}>
                  <optgroup label="Female">
                    {SARVAM_VOICES.filter(v => v.gender === "female").map(v => (
                      <option key={v.id} value={v.id}>{v.label}</option>
                    ))}
                  </optgroup>
                  <optgroup label="Male">
                    {SARVAM_VOICES.filter(v => v.gender === "male").map(v => (
                      <option key={v.id} value={v.id}>{v.label}</option>
                    ))}
                  </optgroup>
                </select>
              </div>
            </div>
          </section>

          {/* Output device — playback routing */}
          <section className="side-section">
            <h4>Audio device</h4>
            <div className="field">
              <label>Playback device</label>
              <div className="select-wrap">
                <select value={outputDeviceId} onChange={e => setOutputDeviceId(e.target.value)}>
                  <option value="">System default</option>
                  {outputDevices.map(d => (
                    <option key={d.deviceId} value={d.deviceId}>
                      {d.label || `Output ${d.deviceId.slice(0,6)}`}
                    </option>
                  ))}
                </select>
              </div>
            </div>
            <div className="field">
              <label>
                Output volume
                <span className="val">{Math.round(outputVolume * 100)}%</span>
              </label>
              <input
                type="range" className="slider"
                min="0" max="1" step="0.05"
                value={outputVolume}
                onChange={e => setOutputVolume(parseFloat(e.target.value))}
              />
            </div>
            <button className="btn" style={{width: "100%"}} onClick={stopOutput}>
              <Icon.Stop/> Stop output
            </button>
          </section>
        </aside>

        {/* ── CENTER: stage ──────────────────────────────── */}
        <main className="stage">
          {/* waveform — slim band, ~10vh */}
          <div className="wave-panel wave-panel-slim">
            <WaveformCanvas analyser={analyser} level={level} recording={recording && !paused} accent="var(--saffron)"/>
            <div className="wave-grid"></div>
            <div className="wave-meta-r">
              <DbMeter level={level}/>
            </div>
          </div>

          {/* transport */}
          <div className="transport">
            <div className="t-side">
              <button className="t-btn" disabled={chunks.length === 0 && sessionMs === 0} onClick={resetSession} title="Clear session">
                <Icon.Reset/>
              </button>
              <div style={{
                display:"flex", flexDirection:"column", lineHeight: 1.1, marginLeft: 8,
              }}>
                <span style={{fontFamily:"var(--f-mono)", fontSize: 9.5, letterSpacing:"0.16em", color:"var(--text-3)", textTransform:"uppercase"}}>
                  Output to
                </span>
                <span style={{fontFamily:"var(--f-display)", fontSize: 15}}>
                  {s2sLanguageLabel(outputLang)}
                  {audibleFallbackLang ? ` + ${s2sLanguageLabel(audibleFallbackLang)} audio` : ""}
                </span>
              </div>
            </div>

            <button className={`rec-btn ${recording ? "recording" : ""}`} onClick={toggleRecord}>
              <span className="ico"></span>
              <span className="lbl">{recording ? "stop · space" : "record · space"}</span>
            </button>

            <div className="t-side right">
              <button
                className="t-btn finish-btn"
                disabled={!canFinish}
                onClick={finishSession}
                title={
                  recording
                    ? "Stop recording first, then finish the session"
                    : (chunks.length === 0
                        ? "Record some audio first"
                        : "Seal this session — saves transcript + audio with linked sequence tags")
                }
                style={{
                  padding: "8px 14px",
                  borderRadius: 8,
                  fontFamily: "var(--f-mono)",
                  fontSize: 11,
                  letterSpacing: "0.12em",
                  textTransform: "uppercase",
                  background: canFinish ? "rgba(30,199,138,0.16)" : "rgba(255,255,255,0.04)",
                  color: canFinish ? "#1ec78a" : "rgba(255,255,255,0.35)",
                  border: `1px solid ${canFinish ? "rgba(30,199,138,0.32)" : "rgba(255,255,255,0.08)"}`,
                  cursor: canFinish ? "pointer" : "not-allowed",
                }}
              >
                {finishing ? "finishing…" : "finish"}
              </button>
            </div>
          </div>

          {finishedNotice && (
            <div
              role="status"
              style={{
                marginTop: 10, padding: "10px 14px", borderRadius: 8,
                background: finishedNotice.error ? "rgba(255,93,138,0.12)" : "rgba(30,199,138,0.12)",
                border: `1px solid ${finishedNotice.error ? "rgba(255,93,138,0.32)" : "rgba(30,199,138,0.32)"}`,
                color: finishedNotice.error ? "#ff5d8a" : "#1ec78a",
                fontFamily: "var(--f-mono)", fontSize: 12,
                display: "flex", justifyContent: "space-between", alignItems: "center", gap: 12,
              }}
            >
              <span>
                {finishedNotice.error
                  ? `Couldn't finish session — ${finishedNotice.error}`
                  : `Session #${finishedNotice.sessionId} sealed · ${finishedNotice.segments ?? "?"} segments · ${finishedNotice.outputs ?? "?"} outputs saved.`}
              </span>
              <button
                type="button"
                onClick={dismissFinishedNotice}
                style={{
                  background: "transparent", border: "none",
                  color: "inherit", cursor: "pointer", fontSize: 14, lineHeight: 1,
                }}
                title="Dismiss"
              >✕</button>
            </div>
          )}

          {/* Audio status chips and the LiveOutputStreams pane have been
              folded into the redesigned TranscriptPanel below. Degraded /
              latency / backlog signals still update the underlying state
              for telemetry — they're just no longer rendered as on-stage
              clutter so the transcript can dominate. */}

          {/* Ephemeral speech-to-speech: NO transcript or translated text is
              displayed. The listener records and hears the translated audio;
              nothing textual is shown or stored. The transcript/translation
              panels (TranscriptPanel, LiveOutputStreams) were removed for this
              audio-only mode. Audio playback is driven by the audio queue and
              the waveform/transport controls above. */}
        </main>
      </div>

      <SettingsModal
        open={settingsOpen}
        onClose={() => setSettingsOpen(false)}
        chunkSeconds={chunkSeconds}
        setChunkSeconds={setChunkSeconds}
      />
      {VocabularyDialogComponent && (
        <VocabularyDialogComponent
          open={vocabularyOpen}
          sourceLang={sourceLang}
          onClose={() => setVocabularyOpen(false)}
        />
      )}

    </div>
  );
}

// ── insert sub-forms ────────────────────────────────────────
// SpeakerInsert now delegates to the searchable SpeakerPicker (defined in
// components.jsx). The picker emits the canonical insert payload
// `{kind:"speaker", label, desc}` which we forward to the parent unchanged,
// so the existing TxInsert renderer in transcript.jsx keeps working.
function SpeakerInsert({ onAdd }) {
  return <SpeakerPicker onAdd={onAdd} />;
}

function TitleInsert({ onAdd }) {
  const [text, setText] = useState("");
  const submit = () => {
    if (!text.trim()) return;
    onAdd(text.trim());
    setText("");
  };
  return (
    <>
      <div className="field" style={{marginBottom: 8}}>
        <textarea rows="2" value={text} onChange={e => setText(e.target.value)}
                  placeholder="Chapter title — appears big and bold across every language"
                  style={{resize:"vertical", minHeight: 56}}/>
      </div>
      <div style={{display:"flex", gap: 6}}>
        <button className="btn" style={{flex: "0 0 auto"}}
                onClick={() => { setText("Q&A Session"); }}>
          Q&amp;A
        </button>
        <button className="btn" style={{flex: "0 0 auto"}}
                onClick={() => { setText("Closing remarks"); }}>
          Closing
        </button>
        <button className="btn primary" style={{flex: 1}} onClick={submit}>
          Insert title
        </button>
      </div>
    </>
  );
}

function AnnotInsert({ onAdd }) {
  const [text, setText] = useState("");
  return (
    <>
      <div className="field" style={{marginBottom: 8}}>
        <input type="text" value={text} onChange={e => setText(e.target.value)}
               placeholder="A quick aside…"
               onKeyDown={e => {
                 if (e.key === "Enter" && text.trim()) {
                   onAdd(text.trim());
                   setText("");
                 }
               }}/>
      </div>
      <button className="btn" style={{width: "100%"}}
              onClick={() => { if (text.trim()) { onAdd(text.trim()); setText(""); } }}>
        Insert annotation
      </button>
    </>
  );
}

// ── keyboard shortcut: spacebar to toggle record ───────────
function useSpacebar(toggleRecord) {
  useEffect(() => {
    const onKey = (e) => {
      if (e.code === "Space" && !["INPUT","TEXTAREA","SELECT"].includes(document.activeElement?.tagName)) {
        e.preventDefault();
        toggleRecord();
      }
    };
    window.addEventListener("keydown", onKey);
    return () => window.removeEventListener("keydown", onKey);
  }, [toggleRecord]);
}

window.App = App;
