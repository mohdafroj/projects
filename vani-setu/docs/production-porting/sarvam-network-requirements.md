# Vani Setu — Production Network Requirements for Sarvam

> **Scope:** the firewall / egress rules the **production** Vani Setu host needs so the
> speech-to-speech (S2S) pipeline can reach **Sarvam** (the only external dependency).
> Companion to `docs/production-porting/README.md` (§1 prerequisites, §9 checklist #14–15).

| | |
|---|---|
| **Document** | Sarvam production network requirements |
| **Owner** | Software Manager, SDS (Rajya Sabha) |
| **Status** | Issued — for NIC network/firewall change request |
| **Last updated** | 2026-06-16 |

---

## 1. Summary (what to ask NIC to open)

Vani Setu is sovereign and self-hosted **except** for Sarvam (Saarika v2 STT + translate +
TTS/TTS-WS). The prod host therefore needs **exactly one external destination opened**, plus DNS.

| # | Direction | Source | Destination | Protocol | Port | Purpose |
|---|-----------|--------|-------------|----------|------|---------|
| 1 | **Egress** | Prod app host (`ml-gateway` container) | **`api.sarvam.ai`** (currently `20.235.220.20`, Azure) | TCP / **HTTPS + WSS** | **443** | Sarvam STT (`/speech-to-text`), TTS (`/text-to-speech`), translate/synopsis (`/v1/synopsis`), **and** the streaming TTS-WS / STT-WS sockets (`wss://`, same host, same port) |
| 2 | **Egress** | Prod host | DNS resolver (internal `10.21.217.132:53` or NIC resolver) | UDP/TCP | **53** | Resolve `api.sarvam.ai` |

**That is the entire Sarvam requirement.** No inbound port from Sarvam is needed — every
call is **outbound, client-initiated** (the WebSocket is an outbound HTTP→WS upgrade on 443).

> The public-facing inbound rules for the app itself (TCP 443 + 80→308 redirect on
> `vanisetu.rajyasabha.digital`) are **separate** and covered in the main runbook §6 — they
> are not part of the Sarvam ask.

---

## 2. Destination detail

- **Host:** `api.sarvam.ai` — single front door for both REST and streaming.
  **Confirmed against `sarvamai` SDK v0.1.28** (`SarvamAIEnvironment.PRODUCTION` = `wss://api.sarvam.ai`):
  there is **no separate WS subdomain** — REST and all WebSockets share one host on 443.
  - REST paths in use: `/speech-to-text`, `/text-to-speech`, `/v1/synopsis`.
  - Streaming WS paths (all `wss://api.sarvam.ai`, port 443, ~0.75–1.0 s first-audio):
    - `/text-to-speech/ws` — TTS-WS
    - `/speech-to-text/ws` — STT-WS
    - `/speech-to-text-translate/ws` — combined STT+translate
  - **WSS runs over 443** — no extra port and no extra host to allow-list.
- **Resolved IP (2026-06-16):** `20.235.220.20` — Microsoft **Azure** address space.
- **Auth:** header `api-subscription-key` (env `SARVAM_API_KEY`); base URL `SARVAM_API_BASE`.

### ⚠️ IP volatility — allow the FQDN, not the bare IP
`api.sarvam.ai` is Azure-hosted and its IP **can change without notice**. Firewall rules:

1. **Preferred:** allow-list by **FQDN** (`api.sarvam.ai`) on TCP 443.
2. **If the NIC firewall is IP-only:** pin `20.235.220.20/32` **and** set a monitor that
   re-resolves the FQDN (e.g. hourly) and alerts on change, so the rule can be updated
   before an outage. Optionally widen to the relevant Azure region range as a safety net.

---

## 3. Things that will silently break Sarvam if missed

- **WebSocket upgrade must pass.** Any L7 proxy / firewall on the egress path must allow the
  HTTP `Upgrade: websocket` handshake on 443 and **long-lived idle connections**
  (set idle timeout ≥ ~2–5 min). A proxy that strips `Upgrade` headers kills TTS-WS/STT-WS
  but leaves REST working — the symptom is "S2S falls back to slow path / no streaming."
- **No TLS interception (MITM) on this host.** The `sarvamai` SDK validates Sarvam's TLS
  cert. A corporate egress proxy that re-signs TLS will break the connection. If NIC runs
  egress DPI/TLS-inspection, **exempt `api.sarvam.ai`** from interception.
- **DNS reachability.** If the prod host points only at the internal `sds-dnsmasq`
  (`10.21.217.132:53`), confirm that resolver can forward public queries upstream
  (it forwards to `1.10.10.10`); otherwise `api.sarvam.ai` won't resolve.

---

## 4. Verification (run on the prod host after the rules are opened)

```bash
# DNS resolves
getent ahosts api.sarvam.ai

# TLS/443 reachable (expect a TLS handshake; 401/404 is fine — proves reachability)
curl -sS -o /dev/null -w "connect=%{http_code} time=%{time_total}s\n" https://api.sarvam.ai/

# WebSocket upgrade allowed on 443 (expect HTTP/1.1 101 or 4xx from Sarvam, NOT a hang/timeout)
curl -sS -i --http1.1 -H "Connection: Upgrade" -H "Upgrade: websocket" \
  -H "Sec-WebSocket-Version: 13" -H "Sec-WebSocket-Key: dGhlIHNhbXBsZSBub25jZQ==" \
  https://api.sarvam.ai/ | head -1

# End-to-end: an actual S2S round-trip in-app → first audio in ~0.75–1.0 s (runbook §9 #7)
```

Map to runbook §9 checklist:
- **#8 Sarvam-only:** `ml-gateway` logs show `api.sarvam.ai`; **zero** calls to `10.21.217.132`.
- **#14 No GPU:** host has no GPU and S2S still works (proves Sarvam is an API, not local compute).
- **#15 Egress:** outbound 443 to `api.sarvam.ai` reachable; no other egress required.

---

## Change log

| Date | Author | Change |
|---|---|---|
| 2026-06-16 | Claude Code | Initial Sarvam production network/egress requirements — ports, FQDN/IP, WSS + DNS, MITM/upgrade caveats, verification. |
| 2026-06-16 | Claude Code | Confirmed WS endpoint against `sarvamai` SDK v0.1.28: single host `wss://api.sarvam.ai` (no separate subdomain); listed the three WS paths. Firewall rule `api.sarvam.ai:443` is complete. |

## Sign-off

| Role | Name | Status |
|---|---|---|
| Prepared by | Claude Code | Issued |
| Approved by | Kushal Pathak (SM, SDS) | ☐ Pending |
| NIC network change | — | ☐ Pending |
