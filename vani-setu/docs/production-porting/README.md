# Vani Setu — Production Porting Runbook

> **Audience:** a developer/operator porting **Vani Setu** onto a *fresh* production
> server with **zero tribal knowledge**.
> **Scope:** single-host Docker-Compose stand-up of the full Vani Setu stack, fresh
> data (no dev data carried over), **Sarvam-only** ML, public domain
> `vanisetu.rajyasabha.digital`.
> **This repo is GATED** (`vani/setu`, requires GitLab login). Internal topology is
> documented here on purpose. **No secret VALUES appear in this runbook** — every
> credential is referenced by *name* and by *where it lives*; you provision the value
> yourself. If you ever see a real key/password in a doc under this path, treat it as
> an incident.

| | |
|---|---|
| **Source of truth** | Dev host `10.21.217.17` (`.17`), compose project `sds-dev`, repo root `/home/sds-dev` |
| **Mesh** | `.17` = apps · `.132` = git + registry + tools · `.133` = database of record |
| **Object storage** | **MinIO only** — never AWS S3 / Azure Blob / GCS (data sovereignty, DPDP) |
| **ML** | **Sarvam** (Saarika v2 STT + translate + TTS-WS). indictrans2 on `.132` is a slow fallback — **disabled in prod** |
| **Edge** | Caddy reverse proxy (bind-mount inode gotcha — see §6) |
| **Companion artifacts** | `docs/PROD-DEPLOY.md`, `docker-compose.prodenv.yml`, `.env.prod.example` (on branch `feat/prod-migration-artifacts`) — this runbook supersedes/expands them |
| **Network / firewall** | Sarvam egress ports + IP/FQDN for the NIC change request: see **`docs/production-porting/sarvam-network-requirements.md`** |

---

## 0. Overview & architecture

Vani Setu is an 8-application Docker-Compose stack fronted by Caddy. The signature
feature is **speech-to-speech (S2S)**: a browser mic stream is transcribed, translated,
and re-spoken in a target language with a **first-audio latency floor of ~0.75–1.0 s**
(the Sarvam compute floor for serial STT → translate → TTS).

### Component diagram (prod target — single host)

```
                         Internet (public)
                               │  443 (TLS) / 80 (ACME HTTP-01)
                               ▼
   ┌───────────────────────────────────────────────────────────────────┐
   │  PROD HOST (Docker + Compose v2)        project: sds-dev            │
   │                                                                     │
   │   ┌──────────────┐   serves SPA dist + routes by path              │
   │   │   caddy      │◄── /opt/vanisetu/frontend/dist  (static SPA)    │
   │   │ (edge/TLS)   │                                                  │
   │   └──┬───┬───┬───┬───┬──────────────┘                              │
   │      │   │   │   │   │                                              │
   │  /api│   │/v1│   │/collab   /app,/apps      /minio-audio (signed)   │
   │  /sanctum │   │   │              │                │                 │
   │      ▼   │   ▼   │   ▼          ▼                ▼                 │
   │  ┌───────┐│┌──────────┐ ┌──────────────┐ ┌──────────┐ ┌─────────┐ │
   │  │  web  │││ml-gateway│ │realtime-     │ │ reverb   │ │ minio   │ │
   │  │(nginx)│││(FastAPI, │ │sidecar       │ │(WS push, │ │(object  │ │
   │  └───┬───┘││ Sarvam)  │ │(Node CRDT)   │ │ Laravel) │ │ store)  │ │
   │      │    │└────┬─────┘ └──────┬───────┘ └────┬─────┘ └────┬────┘ │
   │      ▼    │     │              │              │            │      │
   │  ┌───────────┐  │              │              │       buckets:    │
   │  │ app       │◄─┘ (service     │              │   vani-artifacts- │
   │  │(php-fpm,  │     token)      │              │   non-sensitive   │
   │  │ Laravel)  │                 │              │   vani-audio-raw-rs│
   │  └──┬─────┬──┘                 │              │                   │
   │     │     │  worker(horizon)   │              │                   │
   │     │     │  audit(queue:work) │              │                   │
   │     ▼     ▼                    ▼              ▼                   │
   │  ┌───────┐  ┌───────┐                                            │
   │  │postgres│  │ redis │  (cache + queue + sessions; PROD = auth'd) │
   │  └───────┘  └───────┘                                            │
   │  ┌────────────┐                                                  │
   │  │meilisearch │ (optional — only if search is in go-live scope)  │
   │  └────────────┘                                                  │
   └───────────────────────────────│──────────────────────────────────┘
                                    │ outbound HTTPS (egress required)
                                    ▼
                            https://api.sarvam.ai
                         (STT Saarika v2 + translate + TTS-WS)
```

> **Dev vs prod difference:** on the dev host the DB lives on a *separate* host
> (`.133` PG18) and ML can fall back to the Tijori GPU box (`.132`). **In prod both are
> dropped:** Postgres is a co-located container (or your own managed PG) and ML is
> Sarvam-only. The dev edge also fronts `gitlab.sds.local` / a local registry — **none
> of that ships to prod.**

### S2S data flow (mic → STT → translate → TTS → client)

1. Browser captures mic audio, streams it (WebSocket / chunked POST) to the app.
2. **STT** — audio → source-language text via **Sarvam Saarika v2**. The source text is
   persisted to `s2s_segments.source_text` (see §4).
3. **Translate** — source text → target-language text (Sarvam).
4. **TTS** — target text → audio via **Sarvam TTS-WS** (streaming). First audio chunk
   returns at the ~0.75–1.0 s floor.
5. TTS output audio is (optionally) archived to MinIO bucket
   `vani-artifacts-non-sensitive`; source audio to `vani-audio-raw-rs`. The browser
   plays the returned audio.

> The `ml-gateway` (FastAPI) orchestrates steps 2–4 against Sarvam. The
> `realtime-sidecar` (Node) handles CRDT collaboration (`/collab`); `reverb` handles
> Laravel broadcast WebSockets (`/app`, `/apps`).

### Host roles (source mesh — for reference; prod is a single host)

| Host | IP | Role |
|---|---|---|
| `.17` | `10.21.217.17` | **Apps** — the Vani compose stack runs here (this is the porting source) |
| `.132` | `10.21.217.132` | Git + GitLab Container Registry + internal DNS (dnsmasq :53) + indictrans2 GPU fallback |
| `.133` | `10.21.217.133` | **Database of record** — PostgreSQL 18, `vani_setu` DB |

---

## 1. Target server prerequisites

| Item | Requirement |
|---|---|
| OS | Linux x86-64 (Ubuntu 22.04/24.04 LTS validated). |
| CPU / RAM | 4 vCPU / 8 GB minimum; 8 vCPU / 16 GB recommended (php-fpm + horizon + 3 node/python services + co-located PG/Redis/MinIO). |
| Disk | 50 GB+ SSD. Volumes that grow: `postgres_data`, `minio-data`, `redis-data`, `meilisearch-data`, `caddy_data` (ACME + issued certs). |
| **GPU** | **NONE.** Sarvam is a **remote HTTP API** — there is **no local model**, no CUDA, no GPU needed on the prod host. (The only GPU in the system is the `.132` indictrans2 fallback, which is disabled in prod.) |
| Docker | Docker Engine 24+ and **Compose v2** (`docker compose`, not `docker-compose`). |
| Inbound ports | **80** (ACME HTTP-01 challenge + HTTP→HTTPS redirect) and **443** (HTTPS + WSS) open to the internet. Nothing else needs public exposure. |
| Outbound egress | **Required:** HTTPS (443) to **`api.sarvam.ai`**. This is the single sanctioned external dependency. ACME also needs outbound 443 to Let's Encrypt. No other egress required for a self-contained single-host deploy. |
| DNS | `vanisetu.rajyasabha.digital` must resolve publicly to the prod host's public IP (GoDaddy zone — see §6). |
| Time | NTP synced (TLS + audit hash-chain ordering depend on correct clock). |

---

## 2. Secrets & config inventory

All values are **REDACTED**. "Where it currently lives" tells you the source on the dev
host; "Provision on new server" tells you how to mint a fresh prod value. **Never reuse a
dev secret in prod.** The `.env` files (`/home/sds-dev/.env`, `src/.env`,
`ml-gateway/.env`) are **gitignored** and must be recreated on the prod host.

### 2a. App / Laravel (`.env` → consumed by `app`, `worker`, `audit`, `reverb`)

| Var name | Purpose | Currently lives | Provision on new server |
|---|---|---|---|
| `APP_KEY` | Laravel encryption key | dev `/home/sds-dev/.env` | `php artisan key:generate` — `<provision from source>` |
| `APP_ENV` / `APP_DEBUG` | env mode | `.env` | set `production` / `false` |
| `APP_URL` | canonical URL | `.env` | keep `https://vanisetu.rajyasabha.digital` |
| `TRUSTED_PROXIES` | proxy CIDRs | `.env` | set to prod proxy/docker CIDR |
| `DB_PASSWORD` | Postgres password for user `vani` | `.env` + `src/.env` | generate new; set on PG too — `<provision from source>` |
| `REDIS_PASSWORD` | Redis auth (dev was **auth-less**; prod MUST set) | not in dev | generate new — `<provision from source>` |
| `ASR_INGEST_SECRET` | shared secret for ASR ingest endpoint | `.env` | generate new |
| `REALTIME_AUDIT_SECRET` | shared secret app ↔ realtime-sidecar | `.env` | generate new |
| `ML_GATEWAY_SERVICE_TOKEN` | shared secret Laravel ↔ ml-gateway | `.env` + `ml-gateway/.env` | generate new; must match on both |
| `REVERB_APP_ID` / `REVERB_APP_KEY` / `REVERB_APP_SECRET` | Reverb WS app creds | `.env` | generate new triplet |
| `MEILI_MASTER_KEY` | Meilisearch master key (only if search in scope) | `.env` | generate new |
| `SB_IAM_CLIENT_ID` / `SB_IAM_CLIENT_SECRET` | Sabha IAM OAuth client | `.env` | provision from prod Sabha IAM realm |
| `BOOTSTRAP_ADMIN_*` | first-admin seed (email/password/employee id) | `.env` | set for prod bootstrap, then unset |

### 2b. Database TLS (`src/.env`)

| Var name | Purpose | Currently lives | Provision on new server |
|---|---|---|---|
| `DB_SSLMODE` | TLS mode. Dev = `require`; **prod target = `verify-ca`** | `src/.env` | set `verify-ca` (see §4) |
| `DB_SSLROOTCERT` | path to CA that signed the PG server cert | `src/.env` → `/var/www/html/storage/app/certs/devdb-ca.crt` | place prod PG CA at the same in-container path |

### 2c. ML gateway / Sarvam (`ml-gateway/.env`)

| Var name | Purpose | Currently lives | Provision on new server |
|---|---|---|---|
| `SARVAM_API_KEY` | **paid** Sarvam API key (the only paid external dep) | `ml-gateway/.env` | dedicated **prod** Sarvam key — `<provision from source>` |
| `SARVAM_CONCURRENCY_LIMIT` | cap on concurrent Sarvam calls | `ml-gateway/.env` | `16` (measured optimum) — not secret |
| `PROVIDER_PRECEDENCE` | engine order | `ml-gateway/.env` | prod = `["sarvam"]` (Sarvam-only) |
| `PROCEEDINGS_PROVIDER` | engine for proceedings | `ml-gateway/.env` | `sarvam` |
| `WHISPER_ENABLED` | `.132` GPU fallback toggle | `ml-gateway/.env` | **`false`** in prod |
| `TIJORI_WHISPER_URL` / `INDICTRANS2_ENDPOINT_URL` | `.132` fallback endpoints | `ml-gateway/.env` | **omit/unset** in prod |
| `ML_GATEWAY_SERVICE_TOKEN` | shared secret (mirror of 2a) | `ml-gateway/.env` | same value as in `.env` |
| `ARTIFACT_S3_ENDPOINT` | internal MinIO upload URL | `ml-gateway/.env` → `http://vani-setu-minio:9000` | keep (internal docker hostname) |
| `ARTIFACT_S3_PUBLIC_ENDPOINT` | browser-reachable MinIO prefix (presigned GETs) | `ml-gateway/.env` → `https://vanisetu.rajyasabha.digital/minio-audio` | keep |
| `ARTIFACT_S3_ACCESS_KEY` / `ARTIFACT_S3_SECRET_KEY` | **scoped** MinIO creds (`vani-gateway`, NOT root) | `ml-gateway/.env` | mint via scoped-account script (§5) — `<provision from source>` |
| `ARTIFACT_S3_BUCKET` | TTS output bucket | `ml-gateway/.env` → `vani-artifacts-non-sensitive` | keep |
| `MONGODB_TRACE_WRITES_ENABLED` | master "persist artifacts" switch (misnamed; gates MinIO writes) | `ml-gateway/.env` | `true` if you want audio archived; else `audio_url` returns null |

### 2d. App-side MinIO (`src/.env` — listener/source audio path)

| Var name | Purpose | Currently lives | Provision on new server |
|---|---|---|---|
| `VANI_MINIO_ENDPOINT` | MinIO endpoint the app writes to | `src/.env` | internal MinIO URL |
| `VANI_MINIO_ACCESS_KEY` / `VANI_MINIO_SECRET_KEY` | **scoped** app MinIO creds (`vani-app`) | `src/.env` | mint via scoped-account script (§5) — `<provision from source>` |
| `VANI_MINIO_AUDIO_BUCKET` | source-audio bucket | `src/.env` → `vani-audio-raw-rs` | keep |
| `VANI_MINIO_REGION` | region label | `src/.env` | keep (MinIO ignores, but SDK requires) |

### 2e. Prod overlay only (`.env.prod`, `docker-compose.prodenv.yml`)

| Var name | Purpose | Provision |
|---|---|---|
| `MINIO_ROOT_USER` / `MINIO_ROOT_PASSWORD` | MinIO root (bootstrap only; do not use for app/gateway) | generate new — `<provision from source>` |
| `VANI_APP_IMAGE` / `VANI_WEB_IMAGE` / `VANI_MLGW_IMAGE` / `VANI_RTSIDECAR_IMAGE` | registry image refs | set to prod registry + tag (§3) |
| `DB_HOST` | only if using an **external** managed PG (default = co-located `postgres`) | optional |

> **Cert / CA files referenced (paths, not secrets):** dev TLS cert
> `/gov/Integration/SSL_certificate/6714853845_fullchain.pem` + key `rajyasabha.key`
> (emSign wildcard `*.rajyasabha.digital`, expires **2026-07-29**). Prod does **not**
> reuse this file — it uses Let's Encrypt via Caddy ACME (§6).

---

## 3. Image build & supply chain

Images are built by GitLab CI (`.gitlab-ci.yml`, `build:image` jobs using kaniko) and
pushed to the bundled registry. Image names (note the project-nested path):

| Service | Image | Build context / Dockerfile |
|---|---|---|
| `app` (php-fpm Laravel) | `registry.gitlab.sds.local/vani/setu/app` | `.` / `docker/app.Dockerfile` |
| `web` (nginx) | `registry.gitlab.sds.local/vani/setu/web` | `.` / `docker/web.Dockerfile` |
| `ml-gateway` (FastAPI) | `registry.gitlab.sds.local/vani/setu/ml-gateway` | `ml-gateway/` / `ml-gateway/Dockerfile` |
| `realtime-sidecar` (Node) | `registry.gitlab.sds.local/vani/setu/realtime-sidecar` | `realtime-sidecar/` / `realtime-sidecar/Dockerfile` |

Each is tagged `:$CI_COMMIT_SHORT_SHA` **and** `:latest`. `postgres`, `redis`, `minio`,
`meilisearch`, `caddy` are upstream public images (pinned in compose, see §7).

**Choose one supply-chain model:**

- **A — publish to a prod-reachable registry (recommended).** The dev registry
  `registry.gitlab.sds.local` is **not reachable from prod** (internal CA + dnsmasq).
  Re-tag/push the four images to a registry the prod host can pull from, then set the
  `VANI_*_IMAGE` vars in `.env.prod`. Keeps the `pull && up -d --no-build` flow.
- **B — build on the prod host from source.** Use the base `docker-compose.yml` build
  contexts directly (omit `docker-compose.prod.yml`). The host then needs the full
  source tree (`src/`, `ml-gateway/`, `realtime-sidecar/`, `docker/`, `caddy/`,
  `sds-common-core-php/`) and a build toolchain.

> **Frontend** is a *separate* repo (`vani/frontend`, checked out at `/opt/vanisetu`).
> It is built to a static `dist/` and bind-mounted into Caddy at
> `/opt/vanisetu/frontend/dist`. There is **no frontend container in prod** (the dev
> `frontend` vite service is parked behind a `dev` compose profile). Build `dist/`
> ahead of time and place it on the prod host.
>
> ✅ **Resolved (2026-06-16):** the previously-uncommitted UI capture +
> `ModalityBanner` lived on `vani/frontend` branch
> `preflight/prod-migration-frontend-capture`. It is now **merged into `main`**
> (merge commit `6d0b6cb9`, via MR !1), so a `dist/` built from `vani/frontend@main`
> already carries the latest UI. No pre-build branch merge is required anymore.
>
> **Common Core PHP** (`sds-common-core-php`) is checked out **beside** `src/` and
> mounted read-only into `app`/`worker`/`audit`/`reverb` at
> `/var/www/sds-common-core-php`.

---

## 4. Database

The app speaks **PostgreSQL** (`DB_CONNECTION=pgsql`, db `vani_setu`, user `vani`). On
the dev mesh the DB is **PG18 on `.133`** (`/etc/postgresql/18/main`, listening
`0.0.0.0:5432`). **For a fresh single-host prod deploy, use the co-located `postgres`
container** (image `postgres:16-alpine` in base compose) with `DB_HOST=postgres`. Only
set `DB_HOST` to an external host if you are using a managed PG.

### 4a. Provision

- **Co-located (default):** the `postgres` service starts empty with `POSTGRES_DB=vani_setu`,
  `POSTGRES_USER=vani`, `POSTGRES_PASSWORD=${DB_PASSWORD}`. Nothing else to do for the DB
  itself.
- **External managed PG:** create db `vani_setu`, role `vani` with `DB_PASSWORD`, grant
  ownership; set `DB_HOST`/`DB_PORT` in `.env.prod` and delete the `postgres` override in
  the prod overlay.

### 4b. TLS / `verify-ca`

The dev app currently connects with `DB_SSLMODE=require` and
`DB_SSLROOTCERT=/var/www/html/storage/app/certs/devdb-ca.crt` (in-container path). The
**prod target is `verify-ca`** (validate the server cert against a known CA, the SDS DB
standard).

1. Generate/obtain the PG server cert + key on the DB; configure `ssl=on`,
   `ssl_cert_file`, `ssl_key_file` in `postgresql.conf`.
2. Place the **CA cert** (the one that signed the server cert) on the app host so it
   mounts to `/var/www/html/storage/app/certs/devdb-ca.crt` (rename as you like; match
   `DB_SSLROOTCERT`).
3. Set `DB_SSLMODE=verify-ca` and `DB_SSLROOTCERT=<that path>` in `src/.env`.
4. For the **co-located** container, you can run `DB_SSLMODE=disable` on the internal
   docker network (traffic never leaves the host) — but `verify-ca` to an external PG is
   the standard.

### 4c. Migrations

Laravel auto-discovers both the base migration path and the per-module paths.

```bash
VANI exec app php artisan migrate --force
VANI exec app php artisan migrate:status      # verify all applied, none pending
VANI exec app php artisan db:seed --force     # only if bootstrapping the first admin
```

Migration inventory in the repo: **12** base migrations
(`src/database/migrations/` — users, cache, jobs, permissions, personal access tokens,
IAM extensions, **audit_logs + append-only hash-chain triggers**, synopses,
notification dispatches) **plus 25** module migrations under
`src/app/Modules/*/Migrations/` — including the **SpeechToSpeech** module that creates
**`s2s_segments`** (`2026_05_20_100006_create_s2s_tables.php`, expanded by
`2026_05_21_080000_expand_s2s_pipeline_tables.php`, plus recheck/QA-retry index
migrations).

### 4d. `s2s_segments` (the S2S record)

The S2S pipeline persists one row per segment. **`source_text` = the source-language STT
output** (what Saarika v2 transcribed). The model is
`src/app/Modules/SpeechToSpeech/Models/S2sSegment.php`. The `audit_logs` table is
**append-only with hash-chained integrity triggers** — do not bypass them.

> Do **not** copy dev row data. Prod starts with an empty schema (fresh-data decision).

---

## 5. Object storage (MinIO only)

**Never** substitute AWS S3 / Azure / GCS — data must stay on sovereign infra (DPDP).
MinIO is **not** in the base `docker-compose.yml`; it is added by the prod overlay
(`docker-compose.prodenv.yml`), image
`minio/minio:RELEASE.2025-04-22T22-12-26Z`, console on `:9001`, data volume
`minio-data`, root creds `MINIO_ROOT_USER`/`MINIO_ROOT_PASSWORD`.

### Buckets

| Bucket | Written by | Holds |
|---|---|---|
| `vani-artifacts-non-sensitive` | `ml-gateway` (`ARTIFACT_S3_BUCKET`) | **TTS output** audio (the re-spoken target-language audio) |
| `vani-audio-raw-rs` | `app` (`VANI_MINIO_AUDIO_BUCKET`) | **source audio** (raw listener/mic audio) |

### Setup (one-time, post-boot, via `mc`)

1. `mc alias` the running MinIO with the **root** creds.
2. Create both buckets.
3. Create **two scoped users** — `vani-app` and `vani-gateway` — each with a policy
   limited to its bucket(s), **NOT** the MinIO root account. The repo ships
   `scripts/minio-scoped-accounts.sh` (gateway → policy `vani-artifacts-rw` on the TTS
   bucket only).
4. Put the scoped creds into `ml-gateway/.env` (`ARTIFACT_S3_*`) and `src/.env`
   (`VANI_MINIO_*`); restart `ml-gateway` and `app`.

### Access / TTL

- Browser access is **presigned-GET only**. Caddy's `/minio-audio/*` handler **returns
  403 to any request lacking an `X-Amz-Signature` / `Signature` query param** (see §6),
  reverse-proxying signed requests to MinIO and stripping internal `X-Amz-Meta-*`
  headers. Presigned URLs carry `Cache-Control: private, max-age=900`.
- TTL/lifecycle: set a bucket lifecycle expiry on `vani-audio-raw-rs` (source audio) per
  the data-retention policy if required; `vani-artifacts-non-sensitive` is the
  non-sensitive TTS archive.

---

## 6. Reverse proxy & TLS (Caddy)

Caddy (`caddy:2-alpine`, container `vani-setu-caddy`) is the single edge. The dev edge
serves **both** the prod gov cert *and* an internal `gitlab.sds.local` block — **for prod
you keep only the `vanisetu.rajyasabha.digital` server block.**

### Routing map (from `caddy/Caddyfile.private`)

| Path | Upstream |
|---|---|
| `/api/*`, `/sanctum/*` | `web:80` (Laravel) |
| `/v1/*` | `ml-gateway:8000` |
| `/collab`, `/collab/*` (WS) | `realtime-sidecar:1234` |
| `/app/*`, `/apps/*` (WS) | `reverb:8080` |
| `/minio-audio/*` | `vani-setu-minio:9000` — **signed-GET only, else 403** |
| `/storage/s2s/*` | `web:80` |
| SPA (`/`, `/assets/*`, `/build/*`, fallback) | static `/srv/vanisetu-frontend` (the `dist/` mount) |
| blocked (`/.env`, `/.git`, `/vendor`, `/node_modules`, …) | `404` |

Security headers set: HSTS, `X-Content-Type-Options`, `X-Frame-Options: SAMEORIGIN`,
`Referrer-Policy`, `Permissions-Policy` (mic = self), and a strict CSP allowing
`connect-src` to `https://vanisetu.rajyasabha.digital` + `wss://…` (S2S needs the WSS
origin). `request_body max_size` is large to allow audio upload (per-segment S2S cap is
25 MB enforced app-side).

### Prod Caddyfile

Copy `caddy/Caddyfile.private` → `caddy/Caddyfile.prod` and:

- **Keep** the `vanisetu.rajyasabha.digital` server block + all the `vani_routes`.
- **Remove** the `gitlab.sds.local, registry.gitlab.sds.local, …` server block.
- **Remove** the explicit `tls /etc/caddy/gov-ssl/...pem ...key` line and the
  `gov-ssl` / mkcert cert mounts. Let Caddy **auto-manage the Let's Encrypt cert via
  ACME** (just name the site `vanisetu.rajyasabha.digital` with no `tls` file directive).
  Caddy persists the ACME account + issued cert in the `caddy_data` volume — keep it.

The prod overlay rebinds Caddy from the dev `10.21.217.17:18080->80` to **public**
`0.0.0.0:80` and `0.0.0.0:443`, and swaps the volume mounts to use `Caddyfile.prod` +
the `dist/` mount.

### ⚠️ The bind-mount inode trap (critical gotcha)

`caddy/Caddyfile.*` is a **single-file bind mount**. Editors that save via
atomic-rename create a **new inode**, and the container keeps the **stale** one — so
`caddy reload` (or `docker exec ... caddy reload`) reloads the **old** file silently.

> **After editing any `caddy/Caddyfile*`, you MUST `docker restart vani-setu-caddy`.**
> Do **not** rely on `caddy reload`.

### DNS / TLS provisioning

- **DNS:** `vanisetu.rajyasabha.digital` is in the **GoDaddy** zone for
  `rajyasabha.digital` (nameservers `ns35/ns36.domaincontrol.com`). Repoint its **A
  record** to the prod public IP. **Lower the TTL before cutover** (e.g. 300 s) so
  rollback is fast.
- **TLS:** ACME HTTP-01 needs inbound **80** reachable publicly *and* the A record live,
  so issue the cert after DNS points at prod. The dev emSign wildcard cert is **not**
  reused.

---

## 7. Bring-up sequence

> Define a shell helper so every command targets the right overlay set:
> ```bash
> VANI() { docker compose \
>   -f docker-compose.yml \
>   -f docker-compose.prod.yml \      # registry image refs + pull_policy: always
>   -f docker-compose.prodenv.yml \   # public edge, Sarvam-only, Redis auth, MinIO
>   --env-file .env.prod "$@"; }
> ```
> **Never** add `docker-compose.sa1-repoint.yml` — it pins `DB_HOST` to the dev-LAN
> `.133`, unreachable from prod.

```bash
# 0. Place code + frontend dist + Common Core on the host
#    /home/<user>/  (repo root)  ·  /opt/vanisetu/frontend/dist  ·  ./sds-common-core-php

# 1. Config & secrets (fill every value per §2; nothing reused from dev)
cp .env.prod.example .env.prod
cp caddy/Caddyfile.private caddy/Caddyfile.prod   # then edit per §6
cp ml-gateway/.env.example ml-gateway/.env        # fill Sarvam + scoped S3 creds

# 2. Pull (model A) or build (model B)
VANI pull                 # model A: prod-reachable registry
# VANI build              # model B: build on host (omit docker-compose.prod.yml)

# 3. Bring the stack up
VANI up -d

# 4. Database (fresh schema)
VANI exec app php artisan migrate --force
VANI exec app php artisan migrate:status        # all applied
VANI exec app php artisan db:seed --force       # first-admin bootstrap (if applicable)

# 5. MinIO buckets + scoped users (one-time — §5), then:
VANI restart ml-gateway app

# 6. Confirm Caddy picked up the prod file (remember the inode trap)
docker restart vani-setu-caddy
```

### Healthchecks

```bash
VANI ps                                          # all Up, no restart loop
curl -fsS -o /dev/null -w '%{http_code}\n' https://vanisetu.rajyasabha.digital/      # 200
VANI exec app php artisan migrate:status         # 12 base + 25 module migrations applied
docker logs vani-setu-ml-gateway --tail 50       # no Sarvam auth errors; WS enabled
```

### S2S smoke test (real round-trip)

1. Browse to `https://vanisetu.rajyasabha.digital/speech-to-speech`, log in.
2. Speak a short Hindi phrase; pick an English target.
3. **Expect first-audio in ~0.75–1.0 s**; the spoken English plays back.
4. Confirm in `ml-gateway` logs the call went to `api.sarvam.ai` and **NOT** to any
   `10.21.217.132` endpoint (Tijori fallback must be off).
5. If `MONGODB_TRACE_WRITES_ENABLED=true`: a TTS object lands in
   `vani-artifacts-non-sensitive` and a presigned `/minio-audio/...` GET returns 200
   while an unsigned GET returns 403.

---

## 8. Rollback & cutover

- **Cutover** = flip the GoDaddy A record for `vanisetu.rajyasabha.digital` to the prod
  IP **only after** §7 healthchecks + the S2S smoke test pass on prod (test against the
  prod IP via a `hosts` entry or the raw IP before flipping DNS).
- **Rollback** = repoint the A record **back to the dev `.17` edge**. The dev stack stays
  intact and untouched, so rollback is a pure DNS operation. Keep TTL low (≤300 s) around
  the cutover window so propagation is fast.
- **DB rollback:** since prod is fresh-data, there is no data merge — rollback is purely
  "serve from dev again." Snapshot the prod `postgres_data` + `minio-data` volumes before
  any risky change so you can restore prod state independently.

---

## 9. Verification checklist (pass/fail)

| # | Check | Pass criteria |
|---|---|---|
| 1 | `VANI ps` | All services `Up`; none restart-looping |
| 2 | Edge cert | `curl -I https://vanisetu.rajyasabha.digital` → **valid public LE cert**, HSTS + CSP headers present |
| 3 | HTTP→HTTPS | `curl -I http://vanisetu.rajyasabha.digital` → `308` redirect |
| 4 | Migrations | `migrate:status` → all 12 base + 25 module applied, **0 pending** |
| 5 | Login | App login succeeds (bootstrap admin or IAM) |
| 6 | DB TLS | App connects with `DB_SSLMODE=verify-ca` (external PG) or internal-network PG; no TLS errors in logs |
| 7 | S2S round-trip | Hindi→English first-audio in **~0.75–1.0 s**; audio plays |
| 8 | Sarvam-only | ml-gateway logs show `api.sarvam.ai`; **zero** calls to `10.21.217.132` |
| 9 | MinIO ACL | `/minio-audio/<signed>` → 200; unsigned → **403** |
| 10 | Buckets | `vani-artifacts-non-sensitive` + `vani-audio-raw-rs` exist; app/gateway use **scoped** users (not root) |
| 11 | Reverb / CRDT | Live WS channel + `/collab` sync work in-app |
| 12 | Audit chain | Audit-log writes succeed; append-only hash-chain triggers intact |
| 13 | Redis auth | `redis-cli` without password is **rejected** (prod must not be auth-less) |
| 14 | No GPU dep | Host has no GPU and S2S still works (proves Sarvam-is-API) |
| 15 | Egress | Outbound 443 to `api.sarvam.ai` reachable; no other egress required |

---

## Appendix A — Service / port map (verified on `.17`)

| Container | Image | Internal port | Role |
|---|---|---|---|
| `vani-setu-web` | `…/vani/setu/web` (nginx) | 80 | static + reverse to php-fpm |
| `vani-setu-app` | `…/vani/setu/app` (php-fpm) | 9000 | Laravel app |
| `vani-setu-worker` | `…/vani/setu/app` | — | `php artisan horizon` (queues) |
| `vani-setu-audit` | `…/vani/setu/app` | — | `queue:work redis --queue=audit` |
| `vani-setu-reverb` | `…/vani/setu/app` | 8080 | `reverb:start` (broadcast WS) |
| `vani-setu-ml-gateway` | `…/vani/setu/ml-gateway` (FastAPI) | 8000 | Sarvam S2S orchestrator |
| `vani-setu-realtime-sidecar` | `…/vani/setu/realtime-sidecar` (Node) | 1234 | CRDT collab |
| `vani-setu-meilisearch` | `getmeili/meilisearch:v1.10` | 7700 | search (optional) |
| `vani-setu-redis` | `redis:7-alpine` | 6379 | cache/queue/session |
| `vani-setu-postgres` | `postgres:16-alpine` | 5432 | DB (co-located; dev uses PG18 on `.133`) |
| `vani-setu-minio` | `minio/minio:RELEASE.2025-04-22T22-12-26Z` | 9000/9001 | object store |
| `vani-setu-caddy` | `caddy:2-alpine` | 80/443 | edge / TLS |

## Appendix B — Compose overlay cheat-sheet

| File | Purpose | Use in prod? |
|---|---|---|
| `docker-compose.yml` | base stack (build contexts, co-located PG/Redis) | **yes** |
| `docker-compose.prod.yml` | registry image refs, `build: !reset`, `pull_policy: always` | yes (model A) |
| `docker-compose.prodenv.yml` | public edge bind, Sarvam-only ml-gateway, Redis auth, MinIO service | **yes** |
| `docker-compose.sa1-repoint.yml` | pins DB to dev `.133` | **NO — never in prod** |
| `docker-compose.uat.yml` / `.monitoring.yml` / `.rtsearch.yml` / `.mlgw.yml` / `.vault.yml` | dev/uat extras | no |

---

## Change log

| Date | Author | Change |
|---|---|---|
| 2026-06-16 | Claude Code | Initial production porting runbook — full infra bring-up, verified against the live `.17` stack. |
| 2026-06-16 | Claude Code | §3 frontend note: marked the `vani/frontend` `preflight/prod-migration-frontend-capture` go-live blocker **resolved** — merged into `main` (`6d0b6cb9`, MR !1). |

## Sign-off

| Role | Name | Status |
|---|---|---|
| Prepared by | Claude Code | Issued |
| Approved by | Kushal Pathak (SM, SDS) | ☐ Pending |
