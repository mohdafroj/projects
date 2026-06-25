// sarvam.jsx
// Sarvam API client: speech-to-text + text translation.
// Also: simulated "demo mode" when no key is set — produces lorem-style
// transcripts in the right script so the UI is exercisable without a key.

// Full Sarvam catalogue. `audio: true` means Bulbul v3 can speak it; the
// other languages still translate (text only) via mayura/Saaras.
const SARVAM_LANGS = [
  { code: "hi-IN",  latn: "Hindi",     deva: "हिन्दी",     short: "HI",  audio: true  },
  { code: "en-IN",  latn: "English",   deva: "English",   short: "EN",  audio: true  },
  { code: "bn-IN",  latn: "Bengali",   deva: "বাংলা",     short: "BN",  audio: true  },
  { code: "ta-IN",  latn: "Tamil",     deva: "தமிழ்",     short: "TA",  audio: true  },
  { code: "te-IN",  latn: "Telugu",    deva: "తెలుగు",     short: "TE",  audio: true  },
  { code: "kn-IN",  latn: "Kannada",   deva: "ಕನ್ನಡ",     short: "KN",  audio: true  },
  { code: "ml-IN",  latn: "Malayalam", deva: "മലയാളം",    short: "ML",  audio: true  },
  { code: "mr-IN",  latn: "Marathi",   deva: "मराठी",     short: "MR",  audio: true  },
  { code: "gu-IN",  latn: "Gujarati",  deva: "ગુજરાતી",   short: "GU",  audio: true  },
  { code: "pa-IN",  latn: "Punjabi",   deva: "ਪੰਜਾਬੀ",     short: "PA",  audio: true  },
  { code: "od-IN",  latn: "Odia",      deva: "ଓଡ଼ିଆ",      short: "OD",  audio: true  },
  { code: "ur-IN",  latn: "Urdu",      deva: "اردو",       short: "UR",  audio: false },
  { code: "as-IN",  latn: "Assamese",  deva: "অসমীয়া",    short: "AS",  audio: false },
  { code: "brx-IN", latn: "Bodo",      deva: "बड़ो",       short: "BRX", audio: false },
  { code: "doi-IN", latn: "Dogri",     deva: "डोगरी",      short: "DOI", audio: false },
  { code: "kok-IN", latn: "Konkani",   deva: "कोंकणी",    short: "KOK", audio: false },
  { code: "ks-IN",  latn: "Kashmiri",  deva: "कॉशुर",      short: "KS",  audio: false },
  { code: "mai-IN", latn: "Maithili",  deva: "मैथिली",    short: "MAI", audio: false },
  { code: "mni-IN", latn: "Manipuri",  deva: "মৈতৈলোন্",   short: "MNI", audio: false },
  { code: "ne-IN",  latn: "Nepali",    deva: "नेपाली",    short: "NE",  audio: false },
  { code: "sa-IN",  latn: "Sanskrit",  deva: "संस्कृतम्",  short: "SA",  audio: false },
  { code: "sat-IN", latn: "Santali",   deva: "ᱥᱟᱱᱛᱟᱲᱤ",   short: "SAT", audio: false },
  { code: "sd-IN",  latn: "Sindhi",    deva: "سنڌي",       short: "SD",  audio: false },
];

// Bulbul v3 speaker roster, verified against Sarvam's HTTP 400 response
// during the May 2026 PoC. v2 names (anushka/manisha/vidya/arya/abhilash/
// karun/hitesh) get HTTP 400 on v3 — do NOT add them.
// Source: src/app/Modules/SpeechToSpeech/Services/S2sConfigRepository.php
const SARVAM_VOICES = [
  // female (default first)
  { id: "ritu",      label: "Ritu",      gender: "female" },
  { id: "priya",     label: "Priya",     gender: "female" },
  { id: "neha",      label: "Neha",      gender: "female" },
  { id: "pooja",     label: "Pooja",     gender: "female" },
  { id: "simran",    label: "Simran",    gender: "female" },
  { id: "kavya",     label: "Kavya",     gender: "female" },
  { id: "ishita",    label: "Ishita",    gender: "female" },
  { id: "shreya",    label: "Shreya",    gender: "female" },
  { id: "roopa",     label: "Roopa",     gender: "female" },
  { id: "tanya",     label: "Tanya",     gender: "female" },
  { id: "shruti",    label: "Shruti",    gender: "female" },
  { id: "suhani",    label: "Suhani",    gender: "female" },
  { id: "kavitha",   label: "Kavitha",   gender: "female" },
  { id: "rupali",    label: "Rupali",    gender: "female" },
  { id: "niharika",  label: "Niharika",  gender: "female" },
  // male
  { id: "shubh",     label: "Shubh",     gender: "male" },
  { id: "aditya",    label: "Aditya",    gender: "male" },
  { id: "ashutosh",  label: "Ashutosh",  gender: "male" },
  { id: "rahul",     label: "Rahul",     gender: "male" },
  { id: "rohan",     label: "Rohan",     gender: "male" },
  { id: "amit",      label: "Amit",      gender: "male" },
  { id: "dev",       label: "Dev",       gender: "male" },
  { id: "ratan",     label: "Ratan",     gender: "male" },
  { id: "varun",     label: "Varun",     gender: "male" },
  { id: "manan",     label: "Manan",     gender: "male" },
  { id: "sumit",     label: "Sumit",     gender: "male" },
  { id: "kabir",     label: "Kabir",     gender: "male" },
  { id: "aayan",     label: "Aayan",     gender: "male" },
  { id: "advait",    label: "Advait",    gender: "male" },
  { id: "anand",     label: "Anand",     gender: "male" },
  { id: "tarun",     label: "Tarun",     gender: "male" },
  { id: "sunny",     label: "Sunny",     gender: "male" },
  { id: "mani",      label: "Mani",      gender: "male" },
  { id: "gokul",     label: "Gokul",     gender: "male" },
  { id: "vijay",     label: "Vijay",     gender: "male" },
  { id: "mohit",     label: "Mohit",     gender: "male" },
  { id: "rehan",     label: "Rehan",     gender: "male" },
  { id: "soham",     label: "Soham",     gender: "male" },
];

const SARVAM_BASE = "https://api.sarvam.ai";

let SERVER_SESSION_ID = null;
let SERVER_SESSION_PROMISE = null;
let SERVER_SESSION_TARGETS = [];
let SERVER_SESSION_VOICE = null;
const S2S_CLIENT_ERROR_ENDPOINT = "/speech-to-speech/client-errors";
const S2S_CLIENT_ERROR_LIMIT = 20;
const S2S_CLIENT_ERROR_DEDUPE_MS = 30000;
let S2S_CLIENT_ERROR_COUNT = 0;
const S2S_CLIENT_ERROR_DEDUPE = new Map();

function s2sClientErrorCsrfToken() {
  return document.querySelector('meta[name="csrf-token"]')?.content || "";
}

function reportS2SClientError(kind, details = {}) {
  try {
    const fetchFn = window.__S2S_ORIGINAL_FETCH__ || window.fetch;
    if (!fetchFn || S2S_CLIENT_ERROR_COUNT >= S2S_CLIENT_ERROR_LIMIT) return;

    const signature = [
      kind,
      details.message || "",
      details.source || "",
      details.url || "",
      details.status || "",
    ].join("|");
    const now = Date.now();
    const lastReportedAt = S2S_CLIENT_ERROR_DEDUPE.get(signature) || 0;
    if (now - lastReportedAt < S2S_CLIENT_ERROR_DEDUPE_MS) return;
    S2S_CLIENT_ERROR_DEDUPE.set(signature, now);
    S2S_CLIENT_ERROR_COUNT += 1;

    const chunkId = Number(details.chunk_id);
    const payload = {
      kind,
      message: String(details.message || "").slice(0, 1000),
      source: String(details.source || "").slice(0, 255),
      url: String(details.url || window.location.href || "").slice(0, 500),
      status: Number.isFinite(Number(details.status)) ? Number(details.status) : undefined,
      line: Number.isFinite(Number(details.line)) ? Number(details.line) : undefined,
      column: Number.isFinite(Number(details.column)) ? Number(details.column) : undefined,
      stack: String(details.stack || "").slice(0, 8000),
      session_id: SERVER_SESSION_ID || undefined,
      chunk_id: Number.isFinite(chunkId) && chunkId > 0 ? chunkId : undefined,
      language_code: String(details.language_code || "").slice(0, 16) || undefined,
    };
    const body = JSON.stringify(payload);

    fetchFn.call(window, S2S_CLIENT_ERROR_ENDPOINT, {
      method: "POST",
      headers: {
        Accept: "application/json",
        "Content-Type": "application/json",
        "X-Requested-With": "XMLHttpRequest",
        "X-CSRF-TOKEN": s2sClientErrorCsrfToken(),
      },
      body,
      keepalive: body.length < 6000,
    }).catch(() => {});
  } catch (_) {
    // Error reporting must never break chamber audio.
  }
}

(function installS2SClientErrorReporting() {
  if (window.__S2S_CLIENT_ERROR_REPORTING__) return;
  window.__S2S_CLIENT_ERROR_REPORTING__ = true;
  window.reportS2SClientError = reportS2SClientError;

  window.addEventListener("error", (event) => {
    reportS2SClientError("window_error", {
      message: event.message,
      source: event.filename,
      line: event.lineno,
      column: event.colno,
      stack: event.error?.stack,
    });
  });

  window.addEventListener("unhandledrejection", (event) => {
    const reason = event.reason || {};
    reportS2SClientError("unhandledrejection", {
      message: reason.message || String(reason),
      source: "promise",
      stack: reason.stack,
    });
  });

  if (!window.fetch) return;
  const originalFetch = window.fetch.bind(window);
  window.__S2S_ORIGINAL_FETCH__ = originalFetch;
  window.fetch = async (...args) => {
    const request = args[0];
    const url = typeof request === "string"
      ? request
      : (request instanceof URL ? request.toString() : (request?.url || ""));
    try {
      const response = await originalFetch(...args);
      if (
        response.status >= 500 &&
        url.includes("/speech-to-speech/") &&
        !url.includes(S2S_CLIENT_ERROR_ENDPOINT)
      ) {
        reportS2SClientError("fetch_5xx", {
          message: `S2S request failed with HTTP ${response.status}`,
          source: "fetch",
          url,
          status: response.status,
        });
      }
      return response;
    } catch (error) {
      if (url.includes("/speech-to-speech/") && !url.includes(S2S_CLIENT_ERROR_ENDPOINT)) {
        reportS2SClientError("fetch_exception", {
          message: error?.message || String(error),
          source: "fetch",
          url,
          stack: error?.stack,
        });
      }
      throw error;
    }
  };
})();

function resetS2SServerSession() {
  SERVER_SESSION_ID = null;
  SERVER_SESSION_PROMISE = null;
  SERVER_SESSION_TARGETS = [];
  SERVER_SESSION_VOICE = null;
}

function sameTargetSet(a, b) {
  if (a.length !== b.length) return false;
  const sorted = (arr) => [...arr].map(String).sort();
  const sa = sorted(a);
  const sb = sorted(b);
  return sa.every((v, i) => v === sb[i]);
}

async function updateServerSessionTargets(targetLangs, voice) {
  if (!SERVER_SESSION_ID) return;
  const fd = new FormData();
  for (const lang of targetLangs) fd.append("target_langs[]", lang);
  fd.append("primary_target", targetLangs[0]);
  if (voice) fd.append("tts_speaker", voice);
  try {
    await postS2S(`/speech-to-speech/sessions/${SERVER_SESSION_ID}/targets`, fd);
    SERVER_SESSION_TARGETS = [...targetLangs];
    SERVER_SESSION_VOICE = voice || null;
  } catch (error) {
    // If update fails, force a fresh session next chunk so the new target takes effect.
    resetS2SServerSession();
  }
}

function csrfToken() {
  return document.querySelector('meta[name="csrf-token"]')?.getAttribute("content") || "";
}

async function postS2S(path, fd, opts = {}) {
  const res = await fetch(path, {
    method: "POST",
    headers: {
      "Accept": "application/json",
      "X-Requested-With": "XMLHttpRequest",
      "X-CSRF-TOKEN": csrfToken(),
    },
    body: fd,
    signal: opts.signal,
  });
  const payload = (res.headers.get("content-type") || "").includes("application/json")
    ? await res.json()
    : { message: await res.text() };
  if (!res.ok) {
    const error = new Error(payload.message || `Server pipeline ${res.status}`);
    error.status = res.status;
    error.payload = payload;
    throw error;
  }
  return payload;
}

async function getS2S(path, opts = {}) {
  const res = await fetch(path, {
    headers: {
      "Accept": "application/json",
      "X-Requested-With": "XMLHttpRequest",
    },
    signal: opts.signal,
  });
  const payload = (res.headers.get("content-type") || "").includes("application/json")
    ? await res.json()
    : { message: await res.text() };
  if (!res.ok) {
    const error = new Error(payload.message || `Server pipeline ${res.status}`);
    error.status = res.status;
    error.payload = payload;
    throw error;
  }
  return payload;
}

function wait(ms, signal) {
  if (signal?.aborted) return Promise.reject(new DOMException("Aborted", "AbortError"));
  return new Promise((resolve, reject) => {
    const cleanup = () => signal?.removeEventListener("abort", abort);
    const id = setTimeout(() => {
      cleanup();
      resolve();
    }, ms);
    const abort = () => {
      clearTimeout(id);
      cleanup();
      reject(new DOMException("Aborted", "AbortError"));
    };
    if (signal) signal.addEventListener("abort", abort, { once: true });
  });
}

function archiveDeviceToken(deviceId) {
  const value = typeof deviceId === "string" ? deviceId.trim() : "";
  if (!value) return "";
  let hash = 0x811c9dc5;
  for (let i = 0; i < value.length; i += 1) {
    hash ^= value.charCodeAt(i);
    hash = Math.imul(hash, 0x01000193);
  }
  return `browser-${(hash >>> 0).toString(16).padStart(8, "0")}`;
}

function segmentFromPayload(payload, chunkIdx) {
  const segments = payload.session?.segments || payload.segments || [];
  return segments.find(s => Number(s.sequence_no) === Number(chunkIdx))
    || payload.segment
    || segments[segments.length - 1]
    || {};
}

function segmentHasResult(segment) {
  if (!segment || !segment.id) return false;
  if (["processed", "degraded"].includes(segment.status)) return true;
  return (segment.outputs || []).some(output => output.status && output.status !== "provider_pending");
}

async function ensureS2SSession({ sourceLang, targetLangs, voice, deviceId }) {
  if (SERVER_SESSION_ID) return SERVER_SESSION_ID;
  if (SERVER_SESSION_PROMISE) {
    await SERVER_SESSION_PROMISE;
    return SERVER_SESSION_ID;
  }

  const normalizedSource = sourceLang === "unknown" ? "auto" : sourceLang;
  const fd = new FormData();
  fd.append("title", `Live speech-to-speech ${new Date().toISOString()}`);
  fd.append("mode", "live");
  fd.append("input_source", "microphone");
  fd.append("listener_scope", "hybrid");
  fd.append("source_lang", normalizedSource);
  const deviceToken = archiveDeviceToken(deviceId);
  if (deviceToken) fd.append("capture_device_id", deviceToken);
  for (const lang of targetLangs) fd.append("target_langs[]", lang);
  if (voice) fd.append("tts_speaker", voice);

  SERVER_SESSION_PROMISE = postS2S("/speech-to-speech/sessions", fd)
    .then((created) => {
      SERVER_SESSION_ID = created.session?.id || null;
      SERVER_SESSION_TARGETS = [...targetLangs];
      SERVER_SESSION_VOICE = voice || null;
      return created;
    })
    .finally(() => {
      SERVER_SESSION_PROMISE = null;
    });

  await SERVER_SESSION_PROMISE;
  return SERVER_SESSION_ID;
}

function segmentSignature(segment) {
  if (!segment) return "";
  const outputSig = (segment.outputs || [])
    .map(o => `${o.language_code || ""}:${o.status || ""}`)
    .join("|");
  return `${segment.id || ""}#${segment.status || ""}#${outputSig}`;
}

async function pollS2SStatus({ chunkIdx, timeoutMs = 120000, intervalMs = 500, maxIntervalMs = 4000, signal }) {
  // Iter 5 (2026-05-27): exponential backoff on identical-response polls.
  // Status payload is heavy (data: URIs for inline WAV). When the segment
  // signature does not change between polls, double the wait up to 4s; reset
  // on any delta. Keeps fast detection on first-arrival, slows down on stalls.
  // Iter 24: start at 500ms so batched fallback does not add a hidden 2.5s
  // speak-to-hear penalty when SSE streaming is disabled or unavailable.
  if (!SERVER_SESSION_ID) throw new Error("Server pipeline session was not created");

  const deadline = Date.now() + timeoutMs;
  let lastPayload = null;
  let lastSignature = null;
  let currentInterval = intervalMs;
  const statusPath = `/speech-to-speech/sessions/${SERVER_SESSION_ID}/status?sequence_no=${encodeURIComponent(chunkIdx)}`;
  while (Date.now() < deadline) {
    if (signal?.aborted) throw new DOMException("Aborted", "AbortError");
    const payload = await getS2S(statusPath, { signal });
    lastPayload = payload.session ? payload : { session: payload };
    const segment = segmentFromPayload(lastPayload, chunkIdx);
    if (segmentHasResult(segment)) return lastPayload;
    const sessionStatus = lastPayload?.session?.status;
    if (sessionStatus === "finished" || sessionStatus === "failed") {
      // Session terminal; no further polling will yield a result for this chunk.
      break;
    }
    const sig = segmentSignature(segment);
    if (sig && sig === lastSignature) {
      currentInterval = Math.min(currentInterval * 2, maxIntervalMs);
    } else {
      currentInterval = intervalMs;
      lastSignature = sig;
    }
    await wait(currentInterval, signal);
  }

  const segment = lastPayload ? segmentFromPayload(lastPayload, chunkIdx) : null;
  if (segment?.id) return lastPayload;
  if (!signal?.aborted && typeof window.reportS2SClientError === "function") {
    window.reportS2SClientError("status_poll_timeout", {
      message: "Server pipeline timed out while waiting for Sarvam output",
      source: "batched_status_poll",
      url: window.location.href,
      chunk_id: chunkIdx,
    });
  }
  throw new Error("Server pipeline timed out while waiting for Sarvam output");
}

async function serverSpeechToSpeech({ chunkIdx, wav, startSec, audioStartSec, durationSec, overlapSec, sourceLang, targetLangs, voice, deviceId, onPartial, signal }) {
  const t0 = performance.now();
  if (signal?.aborted) throw new DOMException("Aborted", "AbortError");
  await ensureS2SSession({ sourceLang, targetLangs, voice, deviceId });
  const voiceChanged = (voice || null) !== SERVER_SESSION_VOICE;
  if (SERVER_SESSION_ID && (!sameTargetSet(SERVER_SESSION_TARGETS, targetLangs) || voiceChanged)) {
    await updateServerSessionTargets(targetLangs, voice);
    if (!SERVER_SESSION_ID) {
      await ensureS2SSession({ sourceLang, targetLangs, voice, deviceId });
    }
  }

  const fd = new FormData();
  const normalizedSource = sourceLang === "unknown" ? "auto" : sourceLang;
  fd.append("source_lang", normalizedSource);
  fd.append("source_language", normalizedSource);
  const deviceToken = archiveDeviceToken(deviceId);
  if (deviceToken) fd.append("capture_device_id", deviceToken);
  fd.append("audio", wav, `chunk-${chunkIdx}.wav`);
  fd.append("sequence_no", String(chunkIdx));
  const effectiveStartSec = Number.isFinite(audioStartSec) ? audioStartSec : (Number.isFinite(startSec) ? startSec : 0);
  const effectiveDurationSec = Number.isFinite(durationSec) ? durationSec : 0;
  fd.append("start_ms", String(Math.max(0, Math.round(effectiveStartSec * 1000))));
  fd.append("end_ms", String(Math.max(0, Math.round((effectiveStartSec + effectiveDurationSec) * 1000))));
  if (Number.isFinite(overlapSec)) fd.append("overlap_ms", String(Math.max(0, Math.round(overlapSec * 1000))));
  for (const lang of targetLangs) fd.append("target_langs[]", lang);

  let payload;
  try {
    payload = await postS2S(`/speech-to-speech/sessions/${SERVER_SESSION_ID}/segments`, fd, { signal });
  } catch (error) {
    if (error?.name === "AbortError") throw error;
    if (!SERVER_SESSION_ID || (error.status && error.status < 500)) throw error;
    payload = await pollS2SStatus({ chunkIdx, signal });
  }
  if (signal?.aborted) throw new DOMException("Aborted", "AbortError");

  if (!SERVER_SESSION_ID) {
    SERVER_SESSION_ID = payload.session?.id || null;
  }

  const segment = segmentFromPayload(payload, chunkIdx);
  const recvMs = performance.now() - t0;
  const serverMs = typeof segment.latency_ms === "number" ? segment.latency_ms : null;
  const transcript = segment.source_text || "";
  const detectedLang = segment.source_language || sourceLang;
  const outputs = segment.outputs || payload.session?.outputs || [];
  const audioStorage = {
    disk: segment.source_audio?.disk || segment.source_audio_disk || null,
    path: segment.source_audio?.path || segment.source_audio_path || null,
    size: segment.source_audio?.size || segment.source_audio_size || null,
    stored_size: segment.source_audio?.stored_size || null,
    compression: segment.source_audio?.compression || null,
    download_url: segment.source_audio?.download_url || segment.source_audio_download_url || null,
    pruned: !!segment.source_audio?.pruned,
    pruned_at: segment.source_audio?.pruned_at || null,
    pruned_reason: segment.source_audio?.pruned_reason || null,
    pruned_stored_size: segment.source_audio?.pruned_stored_size || null,
    segment_id: segment.id || null,
    start_ms: segment.timing?.start_ms ?? segment.start_ms ?? null,
    end_ms: segment.timing?.end_ms ?? segment.end_ms ?? null,
    duration_ms: segment.timing?.duration_ms ?? (
      Number.isFinite(segment.end_ms) && Number.isFinite(segment.start_ms) ? Math.max(0, segment.end_ms - segment.start_ms) : null
    ),
    overlap_ms: segment.timing?.overlap_ms ?? null,
    edit_locator: segment.edit_locator || null,
    replay_anchor: segment.edit_locator?.replay_anchor || null,
    correction_url: segment.edit_locator?.correction_url || null,
  };

  onPartial && onPartial({
    kind: "stt",
    chunkIdx,
    lang: detectedLang,
    text: segment.approved_transcript || segment.qa_corrected_text || transcript,
    rawText: transcript,
    qaState: segment.qa_state || null,
    correctedText: segment.qa_corrected_text || null,
    sendMs: serverMs ?? recvMs,
    recvMs,
    serverMs,
    audioStorage,
  });

  const results = {};
  for (const output of outputs) {
    const lang = output.language_code;
    // Iter 12 (2026-05-27): prefer the signed MinIO https URL over decoding
    // the parallel audio_base64 into a data: URL. The Laravel statusPayload
    // helper passes `audio_output_path` straight through, so it is already
    // either an https URL or the iter-4 inline data: URL.
    const audioUrl = output.audio_output_path
      || (output.audio_base64 ? `data:${output.audio_mime_type || "audio/wav"};base64,${output.audio_base64}` : null);
    const text = output.text_output || "";
    const failed = output.status === "provider_error";
    const degraded = output.status === "translation_degraded";
    // Iter 14 (2026-05-27): carry the output row id through so the audio
    // queue can hit /speech-to-speech/outputs/{id}/audio-url to re-sign
    // the MinIO URL when its 15-min TTL is about to lapse. Only present
    // on the status-poll / segment-POST payload (statusPayload now ships
    // ``id`` for each output as of iter-14). The SSE streaming path
    // doesn't carry it; those chunks fall back to the embedded URL and
    // re-signing is a no-op there (iter-15 punt).
    const outputId = output.id || null;
    const outputLocator = output.output_locator || null;
    results[lang] = { translated: text, audioUrl, outputId, outputLocator, sendMs: serverMs ?? recvMs, recvMs, serverMs, degraded };
    onPartial && onPartial({
      kind: failed ? "error" : "translate",
      chunkIdx,
      lang,
      text,
      audioUrl,
      outputId,
      outputLocator,
      sendMs: serverMs ?? recvMs,
      recvMs,
      serverMs,
      degraded,
      error: failed ? (output.error_message || "Provider audio/text generation failed") : null,
    });
  }

  return {
    stt: { transcript, detectedLang, sendMs: serverMs ?? recvMs, recvMs, serverMs, raw: payload },
    results,
  };
}

// ── streaming variant: per-sentence TTS playback ─────────────
//
// Feature-flagged opt-in. Calls /api/s2s/sessions/{id}/segments/stream
// (the SSE endpoint backed by ml-gateway's /v1/speech-to-speech/stream)
// and plays each sentence's audio as soon as it arrives, so the first
// sentence starts speaking while later sentences are still being
// synthesised. Falls back to serverSpeechToSpeech() on any failure so a
// disabled flag, expired key, or transient ml-gateway issue degrades
// gracefully back to the batched route.

function isStreamingTtsEnabled() {
  // Default ON as of iteration 2 (2026-05-27): per-sentence SSE streaming
  // is the production path. Opt-OUT via window.S2S_STREAMING_TTS === false
  // or localStorage.s2s_streaming_tts === "0" if the streaming endpoint
  // misbehaves and a tester needs to fall back to the batched route.
  if (typeof window !== "undefined" && window.S2S_STREAMING_TTS === false) return false;
  try {
    if (typeof localStorage !== "undefined" && localStorage.getItem("s2s_streaming_tts") === "0") return false;
  } catch (_) {
    /* localStorage unavailable — keep default-on */
  }
  return true;
}

// MinIO/boto3 default to SigV4 presigning, where expiry is encoded as
// `X-Amz-Date=YYYYMMDDTHHMMSSZ` plus `X-Amz-Expires=<seconds>` rather than
// the SigV2 absolute `Expires=<unix-epoch>`. Parse both shapes so the
// re-sign guard fires on whichever scheme MinIO is configured for.
// Shared on window so app.jsx's ensureFreshAudioUrl uses the same parser.
function presignedExpiryEpoch(url) {
  if (typeof url !== "string") return null;
  const v2 = url.match(/[?&]Expires=(\d+)/);
  if (v2) {
    const n = parseInt(v2[1], 10);
    if (Number.isFinite(n)) return n;
  }
  const d = url.match(/[?&]X-Amz-Date=(\d{8}T\d{6}Z)/);
  const e = url.match(/[?&]X-Amz-Expires=(\d+)/);
  if (d && e) {
    const iso = `${d[1].slice(0, 4)}-${d[1].slice(4, 6)}-${d[1].slice(6, 8)}T${d[1].slice(9, 11)}:${d[1].slice(11, 13)}:${d[1].slice(13, 15)}Z`;
    const start = Math.floor(Date.parse(iso) / 1000);
    const span = parseInt(e[1], 10);
    if (Number.isFinite(start) && Number.isFinite(span)) return start + span;
  }
  return null;
}
if (typeof window !== "undefined") window.presignedExpiryEpoch = presignedExpiryEpoch;

// Iter 16b (2026-05-27): SSE-streamed playback bypasses app.jsx's
// `ensureFreshAudioUrl` because it goes through this self-contained queue.
// Long-paused streamed sessions therefore still 403'd when MinIO presigned
// URLs expired.
async function ensureFreshSseAudioUrl(item) {
  const url = item.dataUrl || item.audio_url;
  if (!url || url.startsWith("data:")) return url;
  const expiresAt = presignedExpiryEpoch(url);
  if (expiresAt == null) return url;
  const remaining = expiresAt - Math.floor(Date.now() / 1000);
  if (remaining > 60) return url;
  try {
    let endpoint = null;
    if (item.audio_key) endpoint = `/speech-to-speech/audio-url?key=${encodeURIComponent(item.audio_key)}`;
    else if (item.outputId) endpoint = `/speech-to-speech/outputs/${item.outputId}/audio-url`;
    if (!endpoint) return url;
    const r = await fetch(endpoint);
    if (!r.ok) return url;
    const json = await r.json();
    return json.audio_url || url;
  } catch (_) {
    return url;
  }
}

// Per-language queue of pre-decoded data URLs. The play loop drains the
// queue sequentially so the user hears sentence 1, then sentence 2, etc.
// without overlap. A simple HTMLAudioElement is sufficient because each
// SSE `audio` frame carries a self-contained WAV — no MediaSource gymnastics
// needed for MVP.
function createSentenceAudioQueue(onProgress, playbackOptions = {}) {
  const queue = [];
  let playing = false;
  let aborted = false;
  let audioEl = null;

  async function playNext() {
    if (aborted || playing || queue.length === 0) return;
    playing = true;
    const item = queue.shift();
    const { language_code, sentence_index, total_sentences } = item;
    // Iter 16b: re-sign expired MinIO URLs before constructing the Audio
    // element. Re-check `aborted` after the await in case the user cancelled
    // mid-fetch (iter-13 cancellation semantics preserved).
    const freshUrl = await ensureFreshSseAudioUrl(item);
    if (aborted) { playing = false; return; }
    audioEl = new Audio(freshUrl);
    audioEl.volume = Math.max(0, Math.min(1, playbackOptions.outputVolume ?? 1));
    if (playbackOptions.outputDeviceId && typeof audioEl.setSinkId === "function") {
      try { await audioEl.setSinkId(playbackOptions.outputDeviceId); } catch (_) { /* keep system default */ }
    }
    try {
      await audioEl.play();
      // Iter-21: report when audio actually starts PLAYING relative to the
      // stream POST (playbackOptions.t0). This is the browser-side ground
      // truth for perceived speak->hear latency — the gateway's first_audio_ms
      // only measures up to the gateway, missing Laravel + network + decode.
      const playMs = (playbackOptions.t0 != null) ? Math.round(performance.now() - playbackOptions.t0) : null;
      const firstForLang = (sentence_index === 0 || sentence_index == null);
      onProgress && onProgress({ kind: "audio_start", language_code, sentence_index, total_sentences, playMs, firstAudio: firstForLang });
    } catch (err) {
      // Autoplay may be blocked until a user gesture. Surface the state
      // so the UI can show a "tap to play" affordance.
      onProgress && onProgress({ kind: "audio_blocked", language_code, sentence_index, error: err?.message });
      playing = false;
      return;
    }
    await new Promise((resolve) => {
      audioEl.addEventListener("ended", resolve, { once: true });
      audioEl.addEventListener("error", resolve, { once: true });
    });
    playing = false;
    audioEl = null;
    onProgress && onProgress({ kind: "audio_end", language_code, sentence_index, total_sentences });
    playNext();
  }

  return {
    enqueue(chunk) {
      queue.push(chunk);
      playNext();
    },
    abort() {
      aborted = true;
      queue.length = 0;
      if (audioEl) {
        try { audioEl.pause(); } catch (_) { /* noop */ }
      }
    },
  };
}

// Phase 1 (Sarvam TTS-WS): plays progressive `audio_chunk` mp3 fragments for
// one language via MediaSource — first chunk lands ~0.75s after speech end.
// Used only when the gateway emits `audio_chunk` frames (enable_sarvam_ws_tts);
// the sentence-WAV queue above stays the path for the HTTP fallback. Defensive:
// on any MSE error it surfaces audio_blocked rather than throwing.
function createChunkAudioQueue(onProgress, playbackOptions = {}) {
  // MSE primary (progressive, ~0.75s first audio). If MediaSource is
  // unsupported or any append/setup step fails, `mseFailed` is set and every
  // chunk is accumulated in `all`; end() then plays one assembled Blob (the WS
  // chunks are valid concatenable MPEG frames, so this always plays — just not
  // progressively). The `started` guard prevents any double playback.
  let aborted = false, started = false, mseFailed = false;
  let audioEl = null, mediaSource = null, sourceBuffer = null, mime = "audio/mpeg";
  const pending = [];
  const all = [];
  const lang = playbackOptions.lang;

  function reportStart() {
    const playMs = (playbackOptions.t0 != null) ? Math.round(performance.now() - playbackOptions.t0) : null;
    onProgress && onProgress({ kind: "audio_start", language_code: lang, sentence_index: 0, playMs, firstAudio: true });
  }
  function mkAudio() {
    const el = new Audio();
    el.volume = Math.max(0, Math.min(1, playbackOptions.outputVolume ?? 1));
    if (playbackOptions.outputDeviceId && typeof el.setSinkId === "function") { try { el.setSinkId(playbackOptions.outputDeviceId); } catch (_) { /* default */ } }
    return el;
  }
  function play(el) {
    started = true;
    el.play().then(reportStart).catch((err) => onProgress && onProgress({ kind: "audio_blocked", language_code: lang, error: err?.message }));
  }
  function pumpMse() {
    if (aborted || mseFailed || !sourceBuffer || sourceBuffer.updating || !pending.length) return;
    try { sourceBuffer.appendBuffer(pending.shift()); }
    catch (e) { mseFailed = true; return; } // end() will blob-fallback
    if (!started) play(audioEl);
  }
  function trySetupMse(frameMime) {
    mime = frameMime || "audio/mpeg";
    if (typeof MediaSource === "undefined" || !MediaSource.isTypeSupported(mime)) { mseFailed = true; return; }
    audioEl = mkAudio();
    mediaSource = new MediaSource();
    audioEl.src = URL.createObjectURL(mediaSource);
    mediaSource.addEventListener("sourceopen", () => {
      if (aborted) return;
      try {
        sourceBuffer = mediaSource.addSourceBuffer(mime);
        sourceBuffer.addEventListener("updateend", pumpMse);
        pumpMse();
      } catch (e) { mseFailed = true; }
    }, { once: true });
  }

  return {
    enqueue(chunk) {
      if (aborted) return;
      mime = chunk.mime || mime;
      all.push(chunk.bytes);
      if (mseFailed) return;                 // accumulate; blob at end()
      if (!mediaSource) trySetupMse(chunk.mime);
      if (mseFailed) return;
      pending.push(chunk.bytes);
      pumpMse();
    },
    end() {
      if (aborted) return;
      if (started) {                          // MSE playing — finalise the stream
        try { if (mediaSource && mediaSource.readyState === "open") mediaSource.endOfStream(); } catch (_) { /* noop */ }
        onProgress && onProgress({ kind: "audio_end", language_code: lang });
        return;
      }
      // MSE never started (unsupported / failed / empty) -> one assembled Blob.
      if (all.length) { const el = mkAudio(); el.src = URL.createObjectURL(new Blob(all, { type: mime })); audioEl = el; play(el); }
    },
    abort() {
      aborted = true; pending.length = 0;
      if (audioEl) { try { audioEl.pause(); } catch (_) { /* noop */ } }
    },
  };
}

async function serverSpeechToSpeechStreaming({ chunkIdx, wav, startSec, audioStartSec, durationSec, overlapSec, sourceLang, targetLangs, voice, deviceId, outputDeviceId, outputVolume, asr, onPartial, signal, registerQueue }) {
  if (signal?.aborted) throw new DOMException("Aborted", "AbortError");
  await ensureS2SSession({ sourceLang, targetLangs, voice, deviceId });
  const voiceChanged = (voice || null) !== SERVER_SESSION_VOICE;
  if (SERVER_SESSION_ID && (!sameTargetSet(SERVER_SESSION_TARGETS, targetLangs) || voiceChanged)) {
    await updateServerSessionTargets(targetLangs, voice);
    if (!SERVER_SESSION_ID) {
      await ensureS2SSession({ sourceLang, targetLangs, voice, deviceId });
    }
  }

  const fd = new FormData();
  const normalizedSource = sourceLang === "unknown" ? "auto" : sourceLang;
  fd.append("source_lang", normalizedSource);
  fd.append("source_language", normalizedSource);
  const deviceToken = archiveDeviceToken(deviceId);
  if (deviceToken) fd.append("capture_device_id", deviceToken);
  fd.append("audio", wav, `chunk-${chunkIdx}.wav`);
  fd.append("sequence_no", String(chunkIdx));
  const effectiveStartSec = Number.isFinite(audioStartSec) ? audioStartSec : (Number.isFinite(startSec) ? startSec : 0);
  const effectiveDurationSec = Number.isFinite(durationSec) ? durationSec : 0;
  fd.append("start_ms", String(Math.max(0, Math.round(effectiveStartSec * 1000))));
  fd.append("end_ms", String(Math.max(0, Math.round((effectiveStartSec + effectiveDurationSec) * 1000))));
  if (Number.isFinite(overlapSec)) fd.append("overlap_ms", String(Math.max(0, Math.round(overlapSec * 1000))));
  for (const lang of targetLangs) fd.append("target_langs[]", lang);
  // ASR pipeline overrides (Batch 1, 2026-05-29). The Laravel controller
  // merges these on top of the session-level Sarvam defaults before
  // calling ml-gateway, so toggling them takes effect on the next chunk.
  // Diarize/timestamps round-trip into stages.stt for now; ml-gateway
  // will start forwarding them to Sarvam in a follow-up.
  if (asr) {
    if (asr.model) fd.append("stt_model", asr.model);
    fd.append("stt_mode", asr.codemix === false ? "transcribe" : "codemix");
    if (asr.diarize) fd.append("stt_diarize", "1");
    if (asr.timestamps) fd.append("stt_timestamps", "1");
  }

  const t0 = performance.now();
  const res = await fetch(`/speech-to-speech/sessions/${SERVER_SESSION_ID}/segments/stream`, {
    method: "POST",
    headers: {
      "Accept": "text/event-stream",
      "X-Requested-With": "XMLHttpRequest",
      "X-CSRF-TOKEN": csrfToken(),
    },
    body: fd,
    signal,
  });

  if (!res.ok || !res.body) {
    const error = new Error(`Stream pipeline ${res.status}`);
    error.status = res.status;
    throw error;
  }

  // Per-language queues so playback ordering is preserved within each
  // language; different languages play independently (rare in practice
  // — most sessions target one language, but the design supports many).
  // Queues are also handed to the caller via registerQueue() so a Stop
  // button can call .abort() on every active queue and silence playback
  // immediately, even for sentences already decoded into memory.
  const queues = new Map();
  const results = {};
  const queueFor = (lang) => {
    if (!queues.has(lang)) {
      const q = createSentenceAudioQueue(onPartial || (() => {}), { outputDeviceId, outputVolume, t0 });
      queues.set(lang, q);
      if (typeof registerQueue === "function") registerQueue(q);
    }
    return queues.get(lang);
  };
  // Phase 1: parallel registry of MediaSource chunk-queues for the TTS-WS path.
  const chunkQueues = new Map();
  const chunkQueueFor = (lang) => {
    if (!chunkQueues.has(lang)) {
      const q = createChunkAudioQueue(onPartial || (() => {}), { outputDeviceId, outputVolume, t0, lang });
      chunkQueues.set(lang, q);
      if (typeof registerQueue === "function") registerQueue(q);
    }
    return chunkQueues.get(lang);
  };

  const reader = res.body.getReader();
  const decoder = new TextDecoder();
  let buffer = "";
  let firstByteMs = null;
  const streamMeta = {};

  // SSE frames are separated by "\n\n". Read raw bytes, split on the
  // delimiter, and parse one frame at a time. A partial frame stays in
  // the buffer until the next read completes it.
  try {
    while (true) {
      const { value, done } = await reader.read();
      if (done) break;
      if (signal?.aborted) {
        try { await reader.cancel(); } catch (_) { /* noop */ }
        // Make sure any sentence already decoded into a queue stops too.
        for (const q of queues.values()) { try { q.abort(); } catch (_) {} }
        throw new DOMException("Aborted", "AbortError");
      }
      if (firstByteMs === null) firstByteMs = performance.now() - t0;
      buffer += decoder.decode(value, { stream: true });
      let idx;
      while ((idx = buffer.indexOf("\n\n")) !== -1) {
        const rawFrame = buffer.slice(0, idx);
        buffer = buffer.slice(idx + 2);
        const frame = parseSseFrame(rawFrame);
        if (!frame) continue;
        handleSseFrame(frame, { onPartial, results, queueFor, chunkQueueFor, chunkIdx, t0, firstByteMs, streamMeta });
      }
    }
  } catch (err) {
    // If the fetch was aborted via the AbortController, surface a clean
    // AbortError so the caller can distinguish "user stopped" from other
    // failures (which fall back to the batched route).
    if (err?.name === "AbortError" || signal?.aborted) {
      for (const q of queues.values()) { try { q.abort(); } catch (_) {} }
      throw err?.name === "AbortError" ? err : new DOMException("Aborted", "AbortError");
    }
    throw err;
  }

  return {
    stt: results.stt || { transcript: "", detectedLang: sourceLang },
    results: results.outputs || {},
    streaming: true,
    firstByteMs,
  };
}

function parseSseFrame(raw) {
  // Minimal SSE parser: only `event:` and `data:` lines, single data line.
  // Multi-line `data:` accumulators aren't used by the ml-gateway emitter
  // (it always single-lines via json.dumps), so this stays small.
  let event = "message";
  let data = "";
  for (const line of raw.split("\n")) {
    if (line.startsWith("event:")) event = line.slice(6).trim();
    else if (line.startsWith("data:")) data += line.slice(5).trim();
  }
  if (!data) return null;
  try {
    return { event, data: JSON.parse(data) };
  } catch (_) {
    return { event, data: null };
  }
}

function streamedOutputLocator(streamMeta, lang, frameData = {}) {
  const startMs = streamMeta.start_ms ?? 0;
  const endMs = streamMeta.end_ms ?? startMs;
  const audioKey = frameData.audio_key || null;
  return {
    session_id: streamMeta.session_id || null,
    segment_id: streamMeta.segment_id || null,
    output_id: null,
    sequence_no: streamMeta.sequence_no || null,
    language_code: lang,
    sentence_index: frameData.sentence_index ?? null,
    total_sentences: frameData.total_sentences ?? null,
    start_ms: startMs,
    end_ms: endMs,
    duration_ms: Math.max(0, endMs - startMs),
    source_replay_anchor: streamMeta.segment_id ? `#s2s-segment-${streamMeta.segment_id}` : null,
    translated_audio_url: frameData.audio_url || null,
    audio_key: audioKey,
    audio_resign_url: audioKey ? `/speech-to-speech/audio-url?key=${encodeURIComponent(audioKey)}` : null,
  };
}

function handleSseFrame(frame, ctx) {
  const { onPartial, results, queueFor, chunkQueueFor, chunkIdx, t0, firstByteMs, streamMeta } = ctx;
  const recvMs = performance.now() - t0;
  switch (frame.event) {
    case "archive": {
      streamMeta.session_id = frame.data?.session_id || streamMeta.session_id || null;
      streamMeta.segment_id = frame.data?.segment_id || streamMeta.segment_id || null;
      streamMeta.sequence_no = chunkIdx;
      streamMeta.start_ms = frame.data?.start_ms ?? streamMeta.start_ms ?? null;
      streamMeta.end_ms = frame.data?.end_ms ?? streamMeta.end_ms ?? null;
      streamMeta.duration_ms = frame.data?.duration_ms ?? streamMeta.duration_ms ?? null;
      onPartial && onPartial({
        kind: "archive",
        chunkIdx,
        audioStorage: {
          disk: frame.data?.disk || null,
          path: frame.data?.path || null,
          size: frame.data?.size || null,
          stored_size: frame.data?.stored_size || null,
          compression: frame.data?.compression || null,
          download_url: frame.data?.download_url || null,
          segment_id: frame.data?.segment_id || null,
          start_ms: frame.data?.start_ms ?? null,
          end_ms: frame.data?.end_ms ?? null,
          duration_ms: frame.data?.duration_ms ?? null,
          overlap_ms: frame.data?.overlap_ms ?? null,
        },
      });
      break;
    }
    case "stt": {
      results.stt = {
        transcript: frame.data?.transcript || "",
        detectedLang: frame.data?.detected_language || null,
        sendMs: firstByteMs,
        recvMs,
      };
      onPartial && onPartial({
        kind: "stt",
        chunkIdx,
        lang: results.stt.detectedLang,
        text: results.stt.transcript,
        sendMs: firstByteMs,
        recvMs,
      });
      break;
    }
    case "translation": {
      const lang = frame.data?.language_code;
      if (!lang) break;
      const outputLocator = streamedOutputLocator(streamMeta, lang, frame.data || {});
      results.outputs = results.outputs || {};
      results.outputs[lang] = {
        translated: frame.data?.text || "",
        audioUrl: null,
        outputLocator,
        sendMs: firstByteMs,
        recvMs,
        degraded: !!frame.data?.translation_degraded,
        sentences: [],
      };
      onPartial && onPartial({
        kind: "translate",
        chunkIdx,
        lang,
        text: frame.data?.text || "",
        outputLocator,
        sendMs: firstByteMs,
        recvMs,
        degraded: !!frame.data?.translation_degraded,
      });
      break;
    }
    case "audio_chunk": {
      // Phase 1 TTS-WS: progressive mp3 fragment for one language. Decode
      // base64 -> bytes and feed the per-language MediaSource queue.
      const lang = frame.data?.language_code;
      const b64 = frame.data?.audio_base64;
      const cmime = frame.data?.audio_mime_type || "audio/mpeg";
      if (!lang || !b64 || !chunkQueueFor) break;
      let bytes;
      try {
        const bin = atob(b64);
        bytes = new Uint8Array(bin.length);
        for (let i = 0; i < bin.length; i++) bytes[i] = bin.charCodeAt(i);
      } catch (_) { break; }
      chunkQueueFor(lang).enqueue({ bytes, mime: cmime });
      if (frame.data?.first) {
        results.outputs = results.outputs || {};
        results.outputs[lang] = results.outputs[lang] || { translated: "", sentences: [] };
        onPartial && onPartial({ kind: "audio_locator", chunkIdx, lang, sendMs: firstByteMs, recvMs, streaming: true });
      }
      break;
    }
    case "audio": {
      const lang = frame.data?.language_code;
      // Iter 12 (2026-05-27): prefer the signed MinIO https URL from the
      // ml-gateway SSE frame; only decode audio_base64 when the URL is
      // missing (older ml-gateway build, MinIO env unset, etc.). The
      // <audio> element handles https:// and data: URIs identically.
      const remoteUrl = frame.data?.audio_url;
      const audioB64 = frame.data?.audio_base64;
      const mime = frame.data?.audio_mime_type || "audio/wav";
      // Iter 16 (2026-05-27): ml-gateway now emits ``audio_key`` alongside
      // the signed URL so the front-end can re-sign expired URLs via the
      // Laravel /speech-to-speech/audio-url proxy. SSE-streamed sentences
      // have no s2s_outputs row (and therefore no outputId), so audio_key
      // is the only handle ensureFreshAudioUrl() has to recover them.
      const audioKey = frame.data?.audio_key || null;
      if (!lang || (!remoteUrl && !audioB64)) break;
      const dataUrl = remoteUrl || `data:${mime};base64,${audioB64}`;
      const outputLocator = streamedOutputLocator(streamMeta, lang, { ...(frame.data || {}), audio_url: dataUrl, audio_key: audioKey });
      results.outputs = results.outputs || {};
      results.outputs[lang] = results.outputs[lang] || { translated: "", sentences: [] };
      results.outputs[lang].audioUrl = dataUrl;
      results.outputs[lang].outputLocator = outputLocator;
      results.outputs[lang].sentences.push({
        index: frame.data?.sentence_index,
        text: frame.data?.sentence_text,
        dataUrl,
        audio_key: audioKey,
        outputLocator,
      });
      onPartial && onPartial({
        kind: "audio_locator",
        chunkIdx,
        lang,
        audioUrl: dataUrl,
        audioKey,
        outputLocator,
        sendMs: firstByteMs,
        recvMs,
      });
      queueFor(lang).enqueue({
        dataUrl,
        language_code: lang,
        sentence_index: frame.data?.sentence_index,
        total_sentences: frame.data?.total_sentences,
        audio_key: audioKey,
        outputLocator,
      });
      break;
    }
    case "language_done":
      // TTS-WS path: signal end-of-stream to the MediaSource queue so it
      // finalises playback once all chunks have drained.
      if (frame.data?.mode === "ws" && frame.data?.language_code && chunkQueueFor) {
        try { chunkQueueFor(frame.data.language_code).end(); } catch (_) { /* noop */ }
      }
      onPartial && onPartial({ kind: frame.event, chunkIdx, ...(frame.data || {}) });
      break;
    case "language_error":
    case "audio_error":
    case "stream_error":
    case "done":
      onPartial && onPartial({ kind: frame.event, chunkIdx, ...(frame.data || {}) });
      break;
    default:
      break;
  }
}

// ── real API calls ──────────────────────────────────────────
async function sarvamSTT({ wav, sourceLang, apiKey, signal }) {
  const fd = new FormData();
  fd.append("file", wav, `chunk.wav`);
  fd.append("model", "saarika:v2.5");
  // "unknown" lets Sarvam auto-detect; otherwise pass the explicit code
  fd.append("language_code", sourceLang || "unknown");

  const t0 = performance.now();
  const res = await fetch(`${SARVAM_BASE}/speech-to-text`, {
    method: "POST",
    headers: { "api-subscription-key": apiKey },
    body: fd,
    signal,
  });
  const sendMs = performance.now() - t0;

  if (!res.ok) {
    const txt = await res.text().catch(() => "");
    throw new Error(`STT ${res.status}: ${txt.slice(0, 160) || res.statusText}`);
  }
  const data = await res.json();
  const recvMs = performance.now() - t0;
  return {
    transcript: data.transcript || "",
    detectedLang: data.language_code || sourceLang || null,
    sendMs,
    recvMs,
    raw: data,
  };
}

async function sarvamTranslate({ text, sourceLang, targetLang, apiKey, signal }) {
  const t0 = performance.now();
  const res = await fetch(`${SARVAM_BASE}/translate`, {
    method: "POST",
    headers: {
      "api-subscription-key": apiKey,
      "Content-Type": "application/json",
    },
    body: JSON.stringify({
      input: text,
      source_language_code: sourceLang || "auto",
      target_language_code: targetLang,
      speaker_gender: "Male",
      mode: "formal",
      model: "mayura:v1",
      enable_preprocessing: false,
    }),
    signal,
  });
  const sendMs = performance.now() - t0;
  if (!res.ok) {
    const txt = await res.text().catch(() => "");
    throw new Error(`Translate ${res.status}: ${txt.slice(0, 160) || res.statusText}`);
  }
  const data = await res.json();
  const recvMs = performance.now() - t0;
  return {
    translated: data.translated_text || "",
    sendMs,
    recvMs,
    raw: data,
  };
}

// ── demo mode: simulated transcript so the UI works without a key ─
const DEMO_LINES = {
  "hi-IN": [
    "नमस्ते सब लोग, आज हम वाणीसेतु प्लेटफ़ॉर्म पर बात कर रहे हैं।",
    "यह भारतीय भाषाओं के लिए बना एक भाषण-से-भाषण प्रणाली है।",
    "हम सर्वम एआई के मॉडल का उपयोग करते हैं।",
    "आप एक साथ कई भाषाओं में अनुवाद देख सकते हैं।",
    "विशेष पाठ डालने के लिए साइडबार बटन का प्रयोग करें।",
    "रिकॉर्डिंग की कुल अवधि ऊपर दिखाई देती है।",
    "हर खंड का प्रसंस्करण समय रियल-टाइम में मापा जाता है।",
  ],
  "en-IN": [
    "Hello everyone, today we are speaking on the Vanisetu platform.",
    "It is a speech-to-speech system built for Indian languages.",
    "We use the Sarvam AI models under the hood.",
    "You can see translations in multiple languages at once.",
    "Use the sidebar buttons to insert special text.",
    "The total recording duration is shown above.",
    "Per-chunk processing time is measured in real time.",
  ],
  "bn-IN": [
    "নমস্কার সবাই, আজ আমরা বাণীসেতু প্ল্যাটফর্মে কথা বলছি।",
    "এটি ভারতীয় ভাষার জন্য তৈরি একটি বক্তৃতা-থেকে-বক্তৃতা সিস্টেম।",
    "আমরা সর্বম এআই মডেল ব্যবহার করি।",
  ],
  "ta-IN": [
    "வணக்கம் அனைவருக்கும், இன்று வாணிசேது தளத்தில் பேசுகிறோம்.",
    "இது இந்திய மொழிகளுக்கான பேச்சு-முதல்-பேச்சு அமைப்பு.",
    "சர்வம் ஏஐ மாதிரிகள் பயன்படுத்தப்படுகின்றன.",
  ],
  "te-IN": [
    "నమస్కారం అందరికీ, ఈరోజు మనం వాణిసేతు ప్లాట్‌ఫామ్‌లో మాట్లాడుతున్నాం.",
    "ఇది భారతీయ భాషల కోసం రూపొందించిన స్పీచ్-టు-స్పీచ్ సిస్టమ్.",
    "మేము సర్వం ఏఐ మోడల్‌లను ఉపయోగిస్తున్నాము.",
  ],
  "kn-IN": [
    "ಎಲ್ಲರಿಗೂ ನಮಸ್ಕಾರ, ಇಂದು ನಾವು ವಾಣಿಸೇತು ಪ್ಲಾಟ್‌ಫಾರ್ಮ್‌ನಲ್ಲಿ ಮಾತನಾಡುತ್ತಿದ್ದೇವೆ.",
    "ಇದು ಭಾರತೀಯ ಭಾಷೆಗಳಿಗಾಗಿ ನಿರ್ಮಿಸಿದ ಭಾಷಣ-ಭಾಷಣ ವ್ಯವಸ್ಥೆ.",
    "ನಾವು ಸರ್ವಂ ಎಐ ಮಾದರಿಗಳನ್ನು ಬಳಸುತ್ತೇವೆ.",
  ],
  "ml-IN": [
    "എല്ലാവർക്കും നമസ്കാരം, ഇന്ന് ഞങ്ങൾ വാണിസേതു പ്ലാറ്റ്‌ഫോമിൽ സംസാരിക്കുകയാണ്.",
    "ഇത് ഇന്ത്യൻ ഭാഷകൾക്കായി നിർമ്മിച്ച സംഭാഷണ-സംഭാഷണ സംവിധാനമാണ്.",
  ],
  "mr-IN": [
    "सर्वांना नमस्कार, आज आपण वाणीसेतू प्लॅटफॉर्मवर बोलत आहोत.",
    "ही भारतीय भाषांसाठी तयार केलेली भाषण-ते-भाषण प्रणाली आहे.",
  ],
  "gu-IN": [
    "બધાને નમસ્કાર, આજે અમે વાણીસેતુ પ્લેટફોર્મ પર વાત કરી રહ્યા છીએ.",
    "આ ભારતીય ભાષાઓ માટે બનાવેલી સ્પીચ-ટુ-સ્પીચ સિસ્ટમ છે.",
  ],
  "pa-IN": [
    "ਸਾਰਿਆਂ ਨੂੰ ਨਮਸਕਾਰ, ਅੱਜ ਅਸੀਂ ਵਾਣੀਸੇਤੁ ਪਲੇਟਫਾਰਮ 'ਤੇ ਗੱਲ ਕਰ ਰਹੇ ਹਾਂ।",
    "ਇਹ ਭਾਰਤੀ ਭਾਸ਼ਾਵਾਂ ਲਈ ਬਣੀ ਇੱਕ ਭਾਸ਼ਣ-ਤੋਂ-ਭਾਸ਼ਣ ਪ੍ਰਣਾਲੀ ਹੈ।",
  ],
  "od-IN": [
    "ସମସ୍ତଙ୍କୁ ନମସ୍କାର, ଆଜି ଆମେ ବାଣୀସେତୁ ପ୍ଲାଟଫର୍ମରେ କଥା ହେଉଛୁ।",
    "ଏହା ଭାରତୀୟ ଭାଷା ପାଇଁ ନିର୍ମିତ ଏକ ବକ୍ତୃତା-ରୁ-ବକ୍ତୃତା ସିଷ୍ଟମ।",
  ],
};

function demoLine(lang, chunkIdx) {
  const arr = DEMO_LINES[lang] || DEMO_LINES["en-IN"];
  return arr[(chunkIdx - 1) % arr.length];
}

async function demoSTT({ chunkIdx, sourceLang }) {
  const sendMs = 80 + Math.random() * 60;
  const procMs = 280 + Math.random() * 320;
  await new Promise(r => setTimeout(r, sendMs + procMs));
  return {
    transcript: demoLine(sourceLang, chunkIdx),
    detectedLang: sourceLang,
    sendMs,
    recvMs: sendMs + procMs,
  };
}
async function demoTranslate({ chunkIdx, targetLang }) {
  const sendMs = 60 + Math.random() * 50;
  const procMs = 180 + Math.random() * 220;
  await new Promise(r => setTimeout(r, sendMs + procMs));
  return {
    translated: demoLine(targetLang, chunkIdx),
    sendMs,
    recvMs: sendMs + procMs,
  };
}

// ── unified: process one chunk → produce results for N target langs ─
async function processChunk({ chunkIdx, wav, startSec, audioStartSec, durationSec, overlapSec, sourceLang, targetLangs, voice, deviceId, outputDeviceId, outputVolume, asr, apiKey, demo, onPartial, signal, registerQueue }) {
  if (!demo) {
    if (isStreamingTtsEnabled()) {
      try {
        return await serverSpeechToSpeechStreaming({
          chunkIdx, wav, startSec, audioStartSec, durationSec, overlapSec, sourceLang, targetLangs, voice, deviceId, outputDeviceId, outputVolume, asr, onPartial, signal, registerQueue,
        });
      } catch (err) {
        // User-initiated aborts must NOT silently fall back to batched —
        // that would re-trigger TTS for sentences the user just silenced.
        if (err?.name === "AbortError" || signal?.aborted) throw err;
        // Any other failure (404 because flag is server-disabled, transient
        // ml-gateway issue, etc.) degrades gracefully to the batched route.
        try { console.warn("[s2s] streaming TTS failed, falling back to batched:", err); } catch (_) {}
      }
    }
    return serverSpeechToSpeech({ chunkIdx, wav, startSec, audioStartSec, durationSec, overlapSec, sourceLang, targetLangs, voice, deviceId, onPartial, signal });
  }

  // 1) STT
  const stt = demo
    ? await demoSTT({ chunkIdx, sourceLang })
    : await sarvamSTT({ wav, sourceLang, apiKey });

  onPartial && onPartial({ kind: "stt", chunkIdx, lang: sourceLang, text: stt.transcript, ...stt });

  // 2) translate in parallel to all NON-source target languages
  const others = targetLangs.filter(l => l !== sourceLang);
  const results = {};
  // include source as identity result
  if (targetLangs.includes(sourceLang)) {
    results[sourceLang] = { translated: stt.transcript, sendMs: 0, recvMs: 0, identity: true };
  }
  await Promise.all(others.map(async (tl) => {
    try {
      const r = demo
        ? await demoTranslate({ chunkIdx, targetLang: tl })
        : await sarvamTranslate({ text: stt.transcript, sourceLang, targetLang: tl, apiKey });
      results[tl] = r;
      onPartial && onPartial({ kind: "translate", chunkIdx, lang: tl, text: r.translated, ...r });
    } catch (e) {
      results[tl] = { error: e.message };
      onPartial && onPartial({ kind: "error", chunkIdx, lang: tl, error: e.message });
    }
  }));

  return { stt, results };
}

window.SARVAM_LANGS = SARVAM_LANGS;
window.SARVAM_VOICES = SARVAM_VOICES;
window.processChunk = processChunk;
window.resetS2SServerSession = resetS2SServerSession;
window.getS2SServerSessionId = () => SERVER_SESSION_ID;
