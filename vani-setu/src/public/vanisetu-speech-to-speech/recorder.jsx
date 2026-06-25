// recorder.jsx
// Live mic capture + waveform drawing + WAV chunk encoding for Sarvam.
//
// Exposes:
//   useRecorder({ chunkSeconds, sampleRate, onChunk, onError })
//     -> { state, start, stop, level, analyser, devices, deviceId, setDeviceId, gain, setGain, error }
//
// Strategy:
//   - getUserMedia → MediaStream
//   - AudioContext + AnalyserNode (for waveform draw)
//   - GainNode for volume monitoring/control
//   - ScriptProcessorNode (yes, deprecated, but ubiquitous + simple) collects raw Float32 PCM
//   - Every chunkSeconds, drain buffer, encode as 16-bit PCM WAV blob, fire onChunk
//
// WAV chunks are what Sarvam's STT endpoint wants.

const { useState, useEffect, useRef, useCallback, useMemo } = React;

// Overlap window (ms) carried from the tail of chunk N to the head of chunk
// N+1. Saaras is weak at the first/last few hundred milliseconds of a standalone
// WAV; a short pre-roll means a word near a phrase boundary is heard with its
// surrounding silence/phonemes in at least one ASR request. This does not delay
// chunk emission, it only adds a small amount of already-captured context.
// Iter 18 (2026-05-29): overlap dropped to zero. Operators reported the
// 100ms seam still caused word-doubling at the phrase boundary on
// Hindi/English code-mix audio; cleaner phrase-length chunking with no
// carry-in proved more legible than trying to preserve boundary words
// via overlap. Saaras v3 codemix is robust enough to handle the seam.
const OVERLAP_MS = 0;

// ── WAV encoder: takes Float32Array PCM @ sampleRate, returns Blob ──
function encodeWAV(samples, sampleRate) {
  const numChannels = 1;
  const bitsPerSample = 16;
  const bytesPerSample = bitsPerSample / 8;
  const blockAlign = numChannels * bytesPerSample;
  const byteRate = sampleRate * blockAlign;
  const dataSize = samples.length * bytesPerSample;
  const buffer = new ArrayBuffer(44 + dataSize);
  const view = new DataView(buffer);

  const writeStr = (offset, s) => {
    for (let i = 0; i < s.length; i++) view.setUint8(offset + i, s.charCodeAt(i));
  };
  writeStr(0, "RIFF");
  view.setUint32(4, 36 + dataSize, true);
  writeStr(8, "WAVE");
  writeStr(12, "fmt ");
  view.setUint32(16, 16, true);              // PCM chunk size
  view.setUint16(20, 1, true);               // PCM format
  view.setUint16(22, numChannels, true);
  view.setUint32(24, sampleRate, true);
  view.setUint32(28, byteRate, true);
  view.setUint16(32, blockAlign, true);
  view.setUint16(34, bitsPerSample, true);
  writeStr(36, "data");
  view.setUint32(40, dataSize, true);

  // float32 → int16
  let offset = 44;
  for (let i = 0; i < samples.length; i++) {
    let s = Math.max(-1, Math.min(1, samples[i]));
    view.setInt16(offset, s < 0 ? s * 0x8000 : s * 0x7fff, true);
    offset += 2;
  }
  return new Blob([buffer], { type: "audio/wav" });
}

// Default VAD endpointing parameters (Iter 21, 2026-06-01). VAD turns the
// fixed `chunkSeconds` window into a *max cap*: a chunk is emitted as soon as
// the speaker pauses (trailing silence ≥ silenceMs after detected speech),
// which both slashes the speak→hear floor and ends chunks on natural clause
// boundaries — that's better input for the translator than an arbitrary cut.
const VAD_DEFAULTS = {
  enabled: true,
  threshold: 0.012,   // RMS above this counts as speech (mic-dependent; tunable)
  silenceMs: 600,     // trailing silence after speech that ends a chunk
  preRollMs: 200,     // leading context carried into the next chunk
  minSpeechMs: 300,   // ignore sub-phoneme blips; don't emit on a cough
};

function useRecorder({
  chunkSeconds = 3,
  targetSampleRate = 16000,
  onChunk,
  onError,
  gain: gainProp = 1,
  vad = {},
} = {}) {
  const [state, setState] = useState("idle"); // idle | recording | error
  const [error, setError] = useState(null);
  const [level, setLevel] = useState(0);       // 0..1 RMS
  const [devices, setDevices] = useState([]);
  const [deviceId, setDeviceId] = useState(null);
  const [analyser, setAnalyser] = useState(null);

  const ctxRef = useRef(null);
  const streamRef = useRef(null);
  const sourceRef = useRef(null);
  const gainRef = useRef(null);
  const procRef = useRef(null);       // ScriptProcessorNode (fallback path)
  const workletRef = useRef(null);    // AudioWorkletNode (preferred path)
  const muteRef = useRef(null);
  const bufferRef = useRef([]);
  const samplesAccumRef = useRef(0);
  const chunkIdxRef = useRef(0);
  const rafRef = useRef(null);
  const analyserRef = useRef(null);
  const flushRef = useRef(null);      // set during start(); called by stop() to emit the final chunk
  // Cumulative count of *new* (non-overlap) audio milliseconds emitted so far.
  // Used to compute monotonic startSec for each chunk while hiding the
  // overlap/pre-roll window from the user-visible timeline.
  const cumulativeEmittedMsRef = useRef(0);
  // Samples carried over from the tail of the previous chunk (overlap/pre-roll
  // window). When non-zero, the head of bufferRef is the previous chunk's last
  // pre-roll — those samples must NOT count as "new" audio when accounting for
  // chunk start times or the stop()-flush dropout threshold.
  const overlapSamplesRef = useRef(0);
  // ── VAD state (only meaningful when vad.enabled) ──
  const hasSpeechRef = useRef(false);     // has this chunk contained speech yet?
  const speechSamplesRef = useRef(0);     // total speech samples this chunk
  const silenceSamplesRef = useRef(0);    // trailing silence samples since last speech

  // Latest VAD config in a ref so the live audio graph sees edits immediately.
  const vadCfgRef = useRef({ ...VAD_DEFAULTS, ...vad });
  useEffect(() => { vadCfgRef.current = { ...VAD_DEFAULTS, ...vad }; },
    [vad.enabled, vad.threshold, vad.silenceMs, vad.preRollMs, vad.minSpeechMs]);

  // keep latest gain in a ref so the live audio graph picks it up immediately
  const gainValRef = useRef(gainProp);
  useEffect(() => { gainValRef.current = gainProp;
    if (gainRef.current) gainRef.current.gain.value = gainProp;
  }, [gainProp]);

  // device list (best-effort, may need permission first)
  const refreshDevices = useCallback(async () => {
    try {
      const all = await navigator.mediaDevices.enumerateDevices();
      setDevices(all.filter(d => d.kind === "audioinput"));
    } catch (e) { /* ignore */ }
  }, []);
  useEffect(() => { refreshDevices(); }, [refreshDevices]);

  // ── start ──────────────────────────────────────────────────
  const start = useCallback(async () => {
    if (state === "recording") return;
    setError(null);
    try {
      const constraints = {
        audio: deviceId
          ? { deviceId: { exact: deviceId }, echoCancellation: true, noiseSuppression: true, autoGainControl: false }
          : { echoCancellation: true, noiseSuppression: true, autoGainControl: false }
      };
      const stream = await navigator.mediaDevices.getUserMedia(constraints);
      streamRef.current = stream;
      refreshDevices(); // labels show up after permission grant

      const ctx = new (window.AudioContext || window.webkitAudioContext)({ sampleRate: targetSampleRate });
      ctxRef.current = ctx;
      const sr = ctx.sampleRate; // browser may not honor target — handle it

      const source = ctx.createMediaStreamSource(stream);
      sourceRef.current = source;

      const gainNode = ctx.createGain();
      gainNode.gain.value = gainValRef.current;
      gainRef.current = gainNode;

      const an = ctx.createAnalyser();
      an.fftSize = 2048;
      an.smoothingTimeConstant = 0.4;
      analyserRef.current = an;
      setAnalyser(an);

      // ── chunk accounting ──
      // VAD off: chunkSamples is the fixed emission window.
      // VAD on:  chunkSamples is the hard MAX cap; the speaker's pause ends
      //          the chunk earlier via the energy endpoint below.
      const chunkSamples = Math.round(chunkSeconds * sr);
      bufferRef.current = [];
      samplesAccumRef.current = 0;
      // chunkIdxRef is intentionally NOT reset here: React keeps prior chunks
      // across record/stop cycles, so reusing ids 1,2,3 would collide as
      // React keys and cause updateChunk(id) to patch the wrong row.
      cumulativeEmittedMsRef.current = 0;
      overlapSamplesRef.current = 0;
      hasSpeechRef.current = false;
      speechSamplesRef.current = 0;
      silenceSamplesRef.current = 0;

      // Keep only the last `keepSamples` of the buffer (used to bound
      // pre-speech silence so we never ship silence-only chunks).
      const trimToTail = (keepSamples) => {
        const total = samplesAccumRef.current;
        if (total <= keepSamples) return;
        const merged = new Float32Array(total);
        let off = 0;
        for (const s of bufferRef.current) { merged.set(s, off); off += s.length; }
        const tail = merged.subarray(total - keepSamples);
        const tailCopy = new Float32Array(tail.length);
        tailCopy.set(tail);
        bufferRef.current = [tailCopy];
        samplesAccumRef.current = keepSamples;
      };

      // Emit the current buffer as one WAV chunk, then seed the next chunk
      // with a short pre-roll tail. Shared by the fixed-window path, the VAD
      // endpoint, the max-cap, and the stop() flush (via flushRef).
      const emitChunk = (opts = {}) => {
        const total = samplesAccumRef.current;
        if (total <= 0) return false;
        const carryIn = overlapSamplesRef.current; // pre-roll inherited from prev chunk
        const newSamples = total - carryIn;
        // 200 samples ≈ 12.5ms @16k — below the shortest phoneme, never a word.
        if (newSamples < 200 && !opts.final) return false;

        const merged = new Float32Array(total);
        let off = 0;
        for (const s of bufferRef.current) { merged.set(s, off); off += s.length; }

        const id = ++chunkIdxRef.current;
        // startSec uses cumulative *new* audio only — pre-roll is invisible to
        // the user-facing timeline so chunk timestamps stay monotonic.
        const startSec = cumulativeEmittedMsRef.current / 1000;
        const audioStartSec = Math.max(0, startSec - (carryIn / sr));
        const wav = encodeWAV(merged, sr);
        onChunk && onChunk({
          id, wav, startSec, audioStartSec, durationSec: total / sr,
          overlapSec: carryIn / sr, sampleRate: sr,
          ...(opts.final ? { final: true } : {}),
        });
        cumulativeEmittedMsRef.current += (Math.max(0, newSamples) / sr) * 1000;

        const cfg = vadCfgRef.current;
        const preRollSamples = cfg.enabled ? Math.round((cfg.preRollMs / 1000) * sr) : 0;
        const tailLen = Math.min(preRollSamples, total);
        if (tailLen > 0) {
          const tail = merged.subarray(total - tailLen);
          const tailCopy = new Float32Array(tail.length);
          tailCopy.set(tail);
          bufferRef.current = [tailCopy];
          samplesAccumRef.current = tailLen;
          overlapSamplesRef.current = tailLen;
        } else {
          bufferRef.current = [];
          samplesAccumRef.current = 0;
          overlapSamplesRef.current = 0;
        }
        hasSpeechRef.current = false;
        speechSamplesRef.current = 0;
        silenceSamplesRef.current = 0;
        return true;
      };
      flushRef.current = () => emitChunk({ final: true });

      // Called once per buffered audio block (from the worklet or the
      // ScriptProcessor). Accumulates samples and decides when to emit.
      const handleBlock = (input) => {
        const slice = new Float32Array(input.length);
        slice.set(input);
        bufferRef.current.push(slice);
        samplesAccumRef.current += slice.length;

        const cfg = vadCfgRef.current;
        if (!cfg.enabled) {
          if (samplesAccumRef.current >= chunkSamples) emitChunk();
          return;
        }

        // energy VAD on this block
        let sum = 0;
        for (let i = 0; i < slice.length; i++) sum += slice[i] * slice[i];
        const rms = Math.sqrt(sum / slice.length);
        const preRollSamples = Math.round((cfg.preRollMs / 1000) * sr);

        if (rms >= cfg.threshold) {
          hasSpeechRef.current = true;
          speechSamplesRef.current += slice.length;
          silenceSamplesRef.current = 0;
        } else if (hasSpeechRef.current) {
          silenceSamplesRef.current += slice.length;
        } else if (samplesAccumRef.current > preRollSamples) {
          // pre-speech silence: keep buffer bounded to the pre-roll window so
          // we don't ship silence-only chunks or grow memory unbounded.
          trimToTail(preRollSamples);
          overlapSamplesRef.current = 0;
        }

        const speechMs = (speechSamplesRef.current / sr) * 1000;
        const silenceMsNow = (silenceSamplesRef.current / sr) * 1000;
        const endpoint = hasSpeechRef.current && speechMs >= cfg.minSpeechMs && silenceMsNow >= cfg.silenceMs;

        if (endpoint) {
          emitChunk();
        } else if (samplesAccumRef.current >= chunkSamples) {
          // hard max-chunk cap reached — flush speech, or trim runaway silence
          if (hasSpeechRef.current) emitChunk();
          else { trimToTail(preRollSamples); overlapSamplesRef.current = 0; }
        }
      };

      // ── data pump: prefer AudioWorklet (off-main-thread, jank-free);
      // fall back to the deprecated-but-ubiquitous ScriptProcessorNode. ──
      const mute = ctx.createGain();
      mute.gain.value = 0;
      muteRef.current = mute;
      mute.connect(ctx.destination);

      let usingWorklet = false;
      if (ctx.audioWorklet && typeof ctx.audioWorklet.addModule === "function") {
        try {
          const workletCode = `
            class PCMCapture extends AudioWorkletProcessor {
              constructor() { super(); this._buf = []; this._n = 0; this._target = 2048; }
              process(inputs) {
                const ch = inputs[0] && inputs[0][0];
                if (ch) {
                  this._buf.push(ch.slice(0)); this._n += ch.length;
                  if (this._n >= this._target) {
                    const out = new Float32Array(this._n); let o = 0;
                    for (const b of this._buf) { out.set(b, o); o += b.length; }
                    this.port.postMessage(out, [out.buffer]);
                    this._buf = []; this._n = 0;
                  }
                }
                return true;
              }
            }
            registerProcessor('pcm-capture', PCMCapture);
          `;
          const blobUrl = URL.createObjectURL(new Blob([workletCode], { type: "application/javascript" }));
          await ctx.audioWorklet.addModule(blobUrl);
          URL.revokeObjectURL(blobUrl);
          const node = new AudioWorkletNode(ctx, "pcm-capture");
          node.port.onmessage = (e) => handleBlock(e.data);
          workletRef.current = node;
          // graph: source → gain → analyser → worklet → mute → destination
          source.connect(gainNode);
          gainNode.connect(an);
          an.connect(node);
          node.connect(mute);
          usingWorklet = true;
        } catch (e) {
          usingWorklet = false;
        }
      }

      if (!usingWorklet) {
        const proc = ctx.createScriptProcessor(4096, 1, 1);
        procRef.current = proc;
        proc.onaudioprocess = (e) => handleBlock(e.inputBuffer.getChannelData(0));
        // graph: source → gain → analyser → proc → mute → destination
        source.connect(gainNode);
        gainNode.connect(an);
        an.connect(proc);
        proc.connect(mute);
      }

      // RMS level loop
      const dataArr = new Float32Array(an.fftSize);
      const tick = () => {
        an.getFloatTimeDomainData(dataArr);
        let sum = 0;
        for (let i = 0; i < dataArr.length; i++) sum += dataArr[i] * dataArr[i];
        const rms = Math.sqrt(sum / dataArr.length);
        setLevel(Math.min(1, rms * 2.5));
        rafRef.current = requestAnimationFrame(tick);
      };
      rafRef.current = requestAnimationFrame(tick);

      setState("recording");
    } catch (e) {
      setError(e.message || String(e));
      setState("error");
      onError && onError(e);
    }
  }, [state, deviceId, chunkSeconds, targetSampleRate, onChunk, onError, refreshDevices]);

  // ── stop ───────────────────────────────────────────────────
  const stop = useCallback(() => {
    if (rafRef.current) cancelAnimationFrame(rafRef.current);
    rafRef.current = null;

    // flush remaining audio as final chunk via the shared emitter (handles
    // pre-roll accounting + the 200-sample dropout guard identically to the
    // live path). flushRef is set during start().
    if (bufferRef.current.length && ctxRef.current && typeof flushRef.current === "function") {
      try { flushRef.current(); } catch (e) {}
    }
    flushRef.current = null;
    bufferRef.current = [];
    samplesAccumRef.current = 0;
    overlapSamplesRef.current = 0;
    cumulativeEmittedMsRef.current = 0;
    hasSpeechRef.current = false;
    speechSamplesRef.current = 0;
    silenceSamplesRef.current = 0;

    if (workletRef.current) {
      try { workletRef.current.port.onmessage = null; } catch (e) {}
      try { workletRef.current.disconnect(); } catch (e) {}
      workletRef.current = null;
    }
    if (muteRef.current) { try { muteRef.current.disconnect(); } catch (e) {} muteRef.current = null; }
    if (procRef.current) {
      try { procRef.current.disconnect(); } catch (e) {}
      procRef.current.onaudioprocess = null;
      procRef.current = null;
    }
    if (sourceRef.current) { try { sourceRef.current.disconnect(); } catch (e) {} sourceRef.current = null; }
    if (gainRef.current)   { try { gainRef.current.disconnect();  } catch (e) {} gainRef.current   = null; }
    if (analyserRef.current) { try { analyserRef.current.disconnect(); } catch (e) {} analyserRef.current = null; }
    if (ctxRef.current)    { try { ctxRef.current.close(); } catch (e) {} ctxRef.current = null; }
    if (streamRef.current) {
      streamRef.current.getTracks().forEach(t => t.stop());
      streamRef.current = null;
    }
    setAnalyser(null);
    setLevel(0);
    setState("idle");
  }, [onChunk]);

  useEffect(() => () => stop(), []); // unmount cleanup

  return { state, start, stop, level, analyser, devices, deviceId, setDeviceId, error };
}

// ── Live oscilloscope canvas ─────────────────────────────────
function WaveformCanvas({ analyser, level, recording, accent }) {
  const canvasRef = useRef(null);
  const rafRef = useRef(null);
  const historyRef = useRef([]);  // for the "trailing waveform" look
  const HISTORY = 600;

  useEffect(() => {
    const cv = canvasRef.current;
    if (!cv) return;
    const ctx = cv.getContext("2d");

    const resize = () => {
      const dpr = window.devicePixelRatio || 1;
      const r = cv.getBoundingClientRect();
      cv.width  = Math.floor(r.width  * dpr);
      cv.height = Math.floor(r.height * dpr);
      ctx.setTransform(dpr, 0, 0, dpr, 0, 0);
    };
    resize();
    const ro = new ResizeObserver(resize);
    ro.observe(cv);

    const buf = analyser ? new Float32Array(analyser.fftSize) : null;

    const draw = () => {
      const r = cv.getBoundingClientRect();
      const W = r.width, H = r.height;
      ctx.clearRect(0, 0, W, H);

      // ── trailing history (left side: ribbon) ──
      if (recording && buf && analyser) {
        analyser.getFloatTimeDomainData(buf);
        // peak in this frame
        let peak = 0;
        for (let i = 0; i < buf.length; i++) {
          const v = Math.abs(buf[i]);
          if (v > peak) peak = v;
        }
        historyRef.current.push(peak);
        if (historyRef.current.length > HISTORY) historyRef.current.shift();
      } else if (!recording) {
        // decay
        historyRef.current = historyRef.current.map(v => v * 0.95);
      }

      // Draw history as a center-mirrored bar timeline (Audacity-ish)
      const hist = historyRef.current;
      const histW = W * 0.62;
      const barCount = Math.min(hist.length, Math.floor(histW / 3));
      const barW = 2;
      const gap  = 1;
      const cx0  = 12;
      const cy   = H / 2;
      const maxH = H * 0.42;

      for (let i = 0; i < barCount; i++) {
        const v = hist[hist.length - barCount + i];
        const h = Math.max(1, Math.min(maxH, v * maxH * 3));
        const x = cx0 + i * (barW + gap);
        const alpha = 0.55 + 0.45 * (i / barCount);
        ctx.fillStyle = `rgba(255, 230, 180, ${0.18 * alpha})`;
        ctx.fillRect(x, cy - h, barW, h * 2);
      }

      // ── live oscilloscope on the right ──
      if (buf && analyser) {
        analyser.getFloatTimeDomainData(buf);
        const liveX0 = cx0 + barCount * (barW + gap) + 16;
        const liveW  = W - liveX0 - 16;
        if (liveW > 50) {
          // playhead line
          ctx.strokeStyle = "rgba(255, 138, 31, 0.35)";
          ctx.lineWidth = 1;
          ctx.setLineDash([4, 4]);
          ctx.beginPath();
          ctx.moveTo(liveX0, 14);
          ctx.lineTo(liveX0, H - 14);
          ctx.stroke();
          ctx.setLineDash([]);

          // waveform stroke
          ctx.strokeStyle = recording ? accent : "rgba(255, 230, 180, 0.25)";
          ctx.lineWidth = 1.5;
          ctx.shadowColor = recording ? accent : "transparent";
          ctx.shadowBlur = recording ? 6 : 0;
          ctx.beginPath();
          const step = buf.length / liveW;
          for (let i = 0; i < liveW; i++) {
            const v = buf[Math.floor(i * step)] || 0;
            const y = cy + v * maxH * 1.4;
            if (i === 0) ctx.moveTo(liveX0 + i, y);
            else         ctx.lineTo(liveX0 + i, y);
          }
          ctx.stroke();
          ctx.shadowBlur = 0;

          // fill under
          ctx.lineTo(liveX0 + liveW, cy);
          ctx.lineTo(liveX0, cy);
          ctx.closePath();
          ctx.fillStyle = recording
            ? "rgba(255, 138, 31, 0.06)"
            : "rgba(255, 230, 180, 0.03)";
          ctx.fill();
        }
      } else {
        // idle: flat line
        ctx.strokeStyle = "rgba(255, 230, 180, 0.15)";
        ctx.lineWidth = 1;
        ctx.beginPath();
        ctx.moveTo(12, cy);
        ctx.lineTo(W - 12, cy);
        ctx.stroke();
      }

      rafRef.current = requestAnimationFrame(draw);
    };
    rafRef.current = requestAnimationFrame(draw);

    return () => {
      ro.disconnect();
      if (rafRef.current) cancelAnimationFrame(rafRef.current);
    };
  }, [analyser, recording, accent]);

  return <canvas ref={canvasRef} className="wave-canvas" />;
}

window.useRecorder = useRecorder;
window.WaveformCanvas = WaveformCanvas;
