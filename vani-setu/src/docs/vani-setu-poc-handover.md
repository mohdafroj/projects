# Vani Setu — Windows PoC handover (Ankit Chansoria, 2026-05-21)

> Source: `Vani Setu Speech to Speech.zip` (284 MB) uploaded to Google Drive on
> 2026-05-21 by ankit.chansoria@rajyasabha.digital. The zip itself contains a
> standalone Python 3.11 / PySide6 desktop app that targets one PC per output
> language. The Laravel application here (`src/`) is a different architecture
> (server-side pipeline), so the source code does not transplant. What transfers
> is the **settled API knowledge** captured below.
>
> **Merge trail (what landed in this repo from the PoC):**
> - `src/config/services.php` — new `sarvam` block (endpoints, models, modes, codec)
> - `src/app/Modules/SpeechToSpeech/Services/SarvamSpeechPipeline.php` — gotcha comments + per-stage params forwarded to the internal pipeline URL
> - `src/app/Modules/SpeechToSpeech/Services/S2sConfigRepository.php` — Bulbul v3 voice roster + Sarvam constants in `defaults()`
>
> What was deliberately NOT merged:
> - The Python/Qt source (would be dead code inside a PHP/Laravel app)
> - Windows-specific bits (WASAPI capture, Windows Credential Manager) — the
>   chamber-side capture is a separate operator surface, not part of this
>   server module
> - Diarization knobs (PoC explicitly avoids them on the real-time path)

---

## 1. What it is

Vani Setu is a Windows desktop application that performs **speech-to-speech
simultaneous interpretation** for the Rajya Sabha (upper house of the Indian
Parliament). It listens to chamber audio, transcribes it, translates the
transcript, and speaks the translation out to a Dante channel that feeds
headsets in the chamber.

- **Owner:** Rajya Sabha Secretariat.
- **Deployment shape:** one PC per output language. Four to five PCs run in
  parallel for production; each instance is independent (no central server).
- **Latency target:** 2–4 seconds end-to-end, phrase-by-phrase.
- **Languages:** the 22 scheduled Indian languages + English, both directions.
  Source language is auto-detected by default.

It is not a research prototype. It runs on real hardware in a live chamber.

## 2. Status

Phase 1 (working pipeline against Sarvam APIs). Code is in `src/vani_setu/`;
the Qt entry point is `src/vani_setu/app.py`; the package version is `0.1.0`.
There is no git remote and no CI by design.

## 3. Architecture (one paragraph)

WASAPI capture → `webrtcvad` phrase-cut → Sarvam STT
(`POST /speech-to-text`, model `saaras:v3`, mode `codemix`) → Sarvam Translate
(`POST /translate`, model `mayura:v1`) → Sarvam TTS
(`POST /text-to-speech`, model `bulbul:v3`) → reorder buffer → WASAPI playback.
Each stage runs on its own thread; queues bridge them; a Qt signal layer
streams events into the GUI (`MainWindow` → `ControlsPanel`, `TranscriptView`,
`MembersPanel`).

## 4. Tech stack (PoC)

| Concern | Choice |
|---|---|
| Language | Python 3.11 (pinned via `.python-version`; `uv` fetches it) |
| GUI | PySide6 (Qt 6) |
| Audio I/O | `sounddevice` over WASAPI (Windows-only) |
| VAD | `webrtcvad` mode 2 + silence-guard timer |
| Settings | `pydantic-settings`, JSON under `%APPDATA%/VaniSetu` |
| Secrets | Windows Credential Manager via `keyring` |
| Glossary | SQLite (`%APPDATA%/VaniSetu/glossary.db`) |
| HTTP | `httpx` |
| Packaging | (Phase 4) PyInstaller one-folder + Inno Setup installer |
| Build tool | `uv` (Astral) |

## 5. Sarvam configuration that is settled

These are the values currently wired in the PoC code. Each one is the result
of probing Sarvam's live API and reading the dev doc.

### ASR — `/speech-to-text`

- **Model:** `saaras:v3` (default).
- **Mode:** `codemix` (default).
- **Why codemix:** for Hindi-English mixed speech, this is the only mode that
  keeps English words in Latin script and Hindi in Devanagari in the same
  transcript. `transcribe` will normalise; `saarika:v2.5` (the other live
  model) tends to transliterate English into Devanagari and silently ignores
  the `mode` field.
- **Other modes available on saaras:v3:** `transcribe`, `translate`,
  `verbatim`, `translit`. Exposed in the GUI dropdown for runtime selection.
- **Diarization:** **batch-only.** The real-time endpoint rejects
  `with_diarization=true` with HTTP 400. Don't re-add it on the live path.
- **Endpoint:** single URL `https://api.sarvam.ai/speech-to-text`. There is
  no separate `/speech-to-text-translate` in the current API — saaras
  produces English by sending `mode=translate` on the same endpoint.

### Translate — `/translate`

- **Model:** `mayura:v1`. (Only model currently exposed.)
- **Mode:** `formal`.
- Send `enable_preprocessing: true`. Optional `speaker_gender: Male|Female`
  is set from the Members panel selection.
- The PoC sends only the current phrase as `input` — no inlined glossary,
  no rolling context. Glossary post-edit happens after the translation
  returns.

### TTS — `/text-to-speech`

- **Model:** `bulbul:v3`.
- **Pace:** `1.1`. Sample rate `22050`. Codec `wav`.
  `enable_preprocessing: true`.
- **Speakers (v3 only — names from Sarvam's own 400 response):**
  - Male: `shubh`, `aditya`, `ashutosh`, `rahul`, `rohan`, `amit`, `dev`,
    `ratan`, `varun`, `manan`, `sumit`, `kabir`, `aayan`, `advait`, `anand`,
    `tarun`, `sunny`, `mani`, `gokul`, `vijay`, `mohit`, `rehan`, `soham`.
  - Female: `ritu`, `priya`, `neha`, `pooja`, `simran`, `kavya`, `ishita`,
    `shreya`, `roopa`, `tanya`, `shruti`, `suhani`, `kavitha`, `rupali`,
    `niharika`.
- **DO NOT** copy v2 voice names (`anushka`, `manisha`, `vidya`, `arya`,
  `abhilash`, `karun`, `hitesh`) into the v3 list — Sarvam returns HTTP 400.
  v3 has a wholly different speaker roster.
- **Codec stays wav** because the PoC decodes via `wave.open`. Switching to
  `mp3` or `opus` (e.g. for the `/text-to-speech/stream` endpoint) requires
  a chunk-fed player and a decoder — that's a real change, not a parameter
  flip.

### Auth (all three stages)

Header on every call: `api-subscription-key: <key>`. JSON endpoints
(`/translate`, `/text-to-speech`) need `Content-Type: application/json`;
the STT endpoint is `multipart/form-data` (set automatically by `httpx` /
`requests` when you pass `files=`).

## 6. Constraints and "do not do" list

- **Windows-only.** WASAPI for audio I/O and Credential Manager for the API
  key. Don't try to port to Linux/macOS without replacing both. Cross-platform
  is explicitly out of scope for V1.
- **One PC = one output language.** Don't multiplex output languages in one
  process; scale horizontally (more PCs).
- **Phrase-by-phrase, not word-by-word.** Streaming partials are out of
  scope for V1.
- **No central backend, no auth, no GitHub remote.** Sessions, glossary, and
  settings live on the local disk only. Migrate by file copy.
- **Real-time vs batch.** Anything in Sarvam's batch family (diarization,
  `speech_to_text_job.create_job`) cannot live on the live path — it's
  async (submit → poll). It belongs in a separate offline adapter run over
  a saved session WAV.

## 7. Open questions for the Laravel server-side implementation

(Not from the PoC spec — flagged by the merge author.)

1. **Real-time endpoint vs batch.** `ml-gateway/app/adapters/sarvam.py`
   currently POSTs JSON `{audio_url, language_code}` to a configurable
   `sarvam_asr_path`. That shape matches Sarvam's batch endpoints, not the
   real-time `/speech-to-text` multipart endpoint that the PoC uses. Resolve
   before any chamber pilot.
2. **Saarika vs Saaras.** The standing Sarvam-usage policy on this dev host
   was "Saarika v2 only; everything else self-hosted on Tijori." The PoC,
   and the existing `SarvamSpeechPipeline.php`, both target `saaras:v3` +
   `mayura:v1` + `bulbul:v3`. Either the policy has moved, or the module
   needs to swap STT → Saarika v2 + Tijori for translate/TTS. Decision
   needed before contracting Sarvam.
3. **Capture surface.** The PoC's WASAPI capture is operator-side (per PC).
   For the Laravel module the equivalent is browser MediaRecorder /
   audio-uploaded-to-MinIO. The chunking discipline (`webrtcvad` phrase-cut
   + reorder buffer) still applies; port the *idea*, not the bytes.
