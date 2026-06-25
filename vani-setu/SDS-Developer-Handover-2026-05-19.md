# Sampurna Digital Sansad (SDS) — Developer Handover Document

| Field | Value |
|---|---|
| Document ID | SDS-DEV-HANDOVER-2026-05-19 |
| Programme | Sampurna Digital Sansad (सम्पूर्ण डिजिटल संसद) — Rajya Sabha Digital Ecosystem |
| Owner | Software Manager; Kushal Pathak \<kushal.pathak@rajyasabha.digital\> |
| Audience | Incoming engineering developers picking up Setu implementation work |
| Date issued | 2026-05-19 |
| Version | v1.0 (consolidated developer handover) |
| Plan reference | Master Plan v2.2 (`after-the-infra-setup-indexed-swing.md`) |
| Target first go-live | Monsoon Session 2027 |
| Hosting | NIC National Government Cloud (NGC); fully on-premises; data never leaves Indian sovereign cloud |

---

## Live deployments — browser access

The following SDS surfaces are reachable from a browser today. Bookmark these for daily use:

| Setu | URL | Posture |
|---|---|---|
| **Vani Setu** | **https://vanisetu.rajyasabha.digital** | **LIVE.** AI-powered language bridge for transcription, translation, and interpretation across the 22 scheduled languages. First Stage-6 Setu to reach a publicly-resolvable URL. |
| **Tijori Setu** | **https://tijorisetu.rajyasabha.digital** | **LIVE EDGE.** Public browser/API edge for the Tijori document and AI control-plane surface. Internal Setu-to-Setu calls must continue to use the configured in-cluster Tijori service endpoint, not this public hostname. |
| Sabha Setu (public portal) | https://sabha.rajyasabha.digital | Planned (Next.js 15; reserved hostname) |
| Other Setus (Sanchalan, Samiti, Sadasya, Samagrya) | Internal-only at this stage | Stage-6 implementations not yet web-exposed |

Tijori Setu has a public edge at `tijorisetu.rajyasabha.digital` for browser access and external smoke-checks. The AI router remains a service-plane dependency for other Setus; internal callers resolve it through environment/configuration such as `TIJORI_BASE_URL` pointing at the in-cluster service DNS, so the public URL is not hard-coded into application-to-application calls.

---

## Change log

| Rev | Date | Author | Notes |
|---|---|---|---|
| 1.0.1 | 2026-05-20 | Programme Architecture Team | Added Tijori Setu public edge URL `https://tijorisetu.rajyasabha.digital`; clarified that internal Setu callers must keep using configured in-cluster Tijori service discovery rather than hard-coding the public hostname. |
| 1.0 | 2026-05-19 | Programme Architecture Team | First consolidated developer handover. Pulls together architecture, design identity, current state of every Setu, infrastructure laid out, Common Core libraries, Tijori AI router, dev-environment setup, production requirements, pending gaps, and Setu allocation between the in-house engineering line and incoming developers. |

---

## 0. How to read this document

This is a single, self-contained orientation for any developer joining the Sampurna Digital Sansad (SDS) programme. Read it in this order:

1. § 1 — § 3 (what SDS is, the six Setus, the topology)
2. § 4 — § 5 (stage map, what has been built so far)
3. § 6 — § 7 (Common Core libraries and Tijori AI router — every Setu consumes these)
4. § 8 (Design Identity — binding on every UI surface)
5. § 9 — § 10 (allocation: which Setus the incoming developers pick up; dev-environment setup)
6. § 11 — § 13 (acceptance gates, production requirements, sign-off list)

Once you have read this document end-to-end, the further reading order is in § 14.

---

## 1. What Sampurna Digital Sansad (SDS) is

**Sampurna Digital Sansad** (Hindi: सम्पूर्ण डिजिटल संसद, "Complete Digital Parliament") is the integrated digital ecosystem of the **Rajya Sabha**, the Council of States of the Parliament of India. SDS is not a single application; it is a federation of **six Setus** (Sanskrit for "bridges") plus a cross-cutting **AI router**, all running on a substrate of cryptographic, identity, mesh, observability, and policy services, hosted entirely on the NIC National Government Cloud (NGC).

The programme's three binding character statements (from the Design Identity Document, March 2026):

- **Not a single app.** Six interconnected bridges forming one digital Parliament.
- **Not a replacement for human judgement.** It removes friction so judgement operates faster, with better information, and with no language barriers.
- **Not an IT project.** A governance-transformation programme that uses technology as an instrument. Technology serves, never leads.

The vision, verbatim from the canonical charter:

> Every parliamentary function. Every stakeholder. Every language.
> One integrated, secure, digital experience.
> सम्पूर्ण | Complete.

### 1.1 Programme scope at the application layer — Rajya Sabha only

SDS is delivered for the **Rajya Sabha**. No `lok-sabha-*` tenants, clients, or buckets exist in any Setu, Common Core library, or Tijori artefact. The lone exception is the `lok-sabha` realm shell at the IAM-infrastructure layer (ADR-4.4 realm-isolation strategy) which provisions an empty realm container so Bills marked `house_of_introduction = lok_sabha` reflect parliamentary reality.

### 1.2 Sovereignty posture

Data **never leaves** the NIC National Government Cloud. **MinIO erasure-coded object storage** is the only object store; AWS S3 / Azure Blob / GCS are forbidden across the entire programme. This posture is enforced at the egress, mesh, OPA, and audit layers.

---

## 2. The seven Setus

The programme has **six application Setus** plus **one cross-cutting AI router (Tijori)**. Each Setu has a Hindi name, a unique colour from the Setu palette, a unique icon, and a defined role.

| Setu | Devanagari | Role | Stack | Colour (master-handoff) |
|---|---|---|---|---|
| **Sadasya Setu** | सदस्य सेतु | Members' digital companion (mobile) | Flutter 3.24 + Kotlin/Swift native modules | Parliamentary blue `#0E4A99` |
| **Sanchalan Setu** | संचालन सेतु | Secretariat workflow engine (apex orchestrator) | Laravel 12 + Vue 3 + Vite + PostgreSQL 16 + Redis 7 | Teal `#035A48` |
| **Vani Setu** | वाणी सेतु | AI-powered language bridge: transcription, translation, interpretation across 22 scheduled languages (GPU container) | FastAPI + IndicWhisper / Whisper-large-v3 (Sansad-TV fine-tune) + IndicTrans2 + Triton + vLLM | Warm coral `#A23A1F` |
| **Sabha Setu** | सभा सेतु | Running the House: public portal + chamber backend | Next.js 15 + NestJS 10 + PostgreSQL | Crimson `#7A1212` |
| **Samagrya Setu** | समग्र सेतु | Ministry-meets-Parliament: 148 document categories; Mayan EDMS; LARDIS replacement | NestJS 10 + PostgreSQL 16 + Mayan EDMS | Deep green `#1F4D00` |
| **Samiti Setu** | समिति सेतु | Secure committee intelligence: DRPSCs, witness depositions, in-camera briefings, pre-publication drafts | NestJS 10 + PostgreSQL + Redis 7 (hardened) | Deep purple `#2B248E` |
| **Tijori Setu** | तिजोरी सेतु | Sovereign AI control plane: routes the right model to the right Setu for the right task; budget gate for paid AI API spend; on-prem-first | Python 3.12 + FastAPI + Triton + vLLM | Antique gold `#8A5F12` |

Crimson (Sabha) is the programme-wide primary system colour. Gold (Tijori) is the programme-wide accent for AI-affordance surfaces. Every other Setu paints only its own surfaces — never another's.

### 2.1 Setu topology (information flow)

- **Sanchalan** is the workflow apex. Every file noting, agenda preparation, roster coordination, and administrative clearance flows through Sanchalan and dispatches to the other Setus.
- **Sabha** is both the public portal (`sabha.rajyasabha.digital`, Next.js 15) and the chamber backend during sittings (proceedings, voting, broadcast coordination).
- **Samiti** and **Sadasya** are presentation-tier surfaces — Samiti for committee members and Committee Branch staff (hardened, in-camera by default), Sadasya for every audience tier on mobile.
- **Vani** is a separate GPU container ingesting floor audio and producing transcripts, translation and interpretation deliveries; downstream Setus consume Vani via Tijori AI-routing rules.
- **Samagrya** is the bidirectional fabric between Union Ministries and the Rajya Sabha Secretariat for 148 document categories; LARDIS replacement.
- **Tijori** is the single AI control plane — **no other Setu loads a model.** This is Section 2 non-negotiable #5 in the Master Architecture.

### 2.2 What is **not** in scope

- Lok Sabha application surfaces (out of scope at the application layer).
- Mass interception primitives, silent SMS, IMEI/SIM-serial device binding, covert tracking. All forbidden by hard rule, in every Setu.
- Cross-Setu repaint or "shared component" overrides of the Setu palette. Each Setu owns its own colour; the family-portrait posture is non-negotiable.

---

## 3. Substrate, applications, and stage discipline

The programme is delivered in **eight numbered stages**. The lower stages build the substrate; the upper stages build applications.

| Stage | Name | Posture |
|---|---|---|
| 1 | Bootstrap | Host floor + monorepo seed (CLOSED 2026-05-12) |
| 2 | Base software | OS hardening, package set, AppArmor baseline (CLOSED 2026-05-13) |
| 3 | Configuration: cryptographic + policy substrate | Vault HA (Raft) + transit auto-unseal, SoftHSM v2, OPA Gatekeeper, Linkerd 2.x mTLS (substantial progress; sub-stage 3A Vault closed; 3B/3C/3D in flight at the time of writing) |
| 4 | Identity & Access Management | Keycloak realm, NIC Parichay OAuth federation, FIDO2 / passkeys (speced; entry-readiness work in flight) |
| 5 | PKI + eSign | SignServer CE + SoftHSM v2 + C-DAC eSign API; per-Setu transit-key namespaces; **dry-run completed 2026-05-18** — SignServer + SoftHSM substrate deployed, end-to-end signing path verified |
| 6 | Setu application code | First Setu application implementations land here (Vani-first, then the rest); **already permitted on a per-Setu basis** for the per-Setu repo pattern (Tijori is already shipping at v0.10.0 outside `apps/` and `services/`) |
| 7 | Inter-Setu choreography + Tijori plane | Tijori router production wiring + cross-Setu workflow choreography + Sarvam budget gate |
| 8 | Production cutover, Monsoon Session 2027 go-live | First production traffic |

### 3.1 The "substrate first" ordering

The programme honours a strict ordering rule: **IAM infrastructure deploys before federation and biometric applications.** Keycloak, Vault, Postgres, mesh, and audit substrate are built and validated first; only then do IdPs and biometric applications layer on top. This rule prevents brittle application-on-shifting-substrate failures during cutover.

### 3.2 Setu repositories versus the monorepo

The programme's working tree is split deliberately:

- The **monorepo** at `~/sds-monorepo/` holds programme-wide governance documents (ADRs, stage specs, runbooks, architecture, design identity), the substrate `infra/` directory (Stage-3 and Stage-4 infrastructure manifests), the cross-Setu `deploy/` directory (Stage-5 signing substrate, Vani Setu deployment), and limited application code where the stage rules permit it (currently only Sadasya governance scaffolding and the Vani Setu services tree).
- **Per-Setu sibling repositories** exist outside the monorepo for Setus whose entry conflict-resolution placed their application code in its own GitLab project. Tijori is the lead example (`sds-tijori-setu/`); the same pattern applies to Common Core (four sibling repos) and the audit-bridge / fake-Parichay support repos.

This split is recorded in ADR-TIJORI-1 (Conflict 1 resolution) and is the operating model for the rest of the programme.

---

## 4. What has been built — infrastructure laid out

The following infrastructure is **deployed and verified** on the bootstrap host (the development cluster). Production cluster bring-up at NIC NGC follows the same manifests with environment-specific overlays.

### 4.1 Stage-1 and Stage-2 (CLOSED)

- **Host floor.** Ubuntu 22.04 LTS; package set; AppArmor profiles baseline; Lynis hardening baseline captured; SSH password-auth flip ceremony completed 2026-05-13.
- **Container runtime.** Docker + containerd.
- **Kubernetes.** k3s v1.29.x; single-node dev cluster; production posture is a 3-node control plane + 2 GPU worker nodes.
- **Registry.** Local registry (Stage-1) plus Harbor planned for Stage-5+ image-signing closure.
- **GitLab CE.** Self-hosted at `gitlab.sds.local`. GPG-signed commits with key fingerprint `6CDA70EA798C6DF660F9363F7A096B6C23D1AAD4` (short ID `7A096B6C23D1AAD4`, Software Manager). Personal-access tokens are pre-provisioned and stored under `~/sds-bootstrap/state/`. The internal CA certificate is at `~/sds-secrets/sds-internal-root-ca.crt`.
- **Argo CD.** Installed for GitOps-driven deployment.
- **Cosign.** Image signing key generated; passphrase stored at `~/sds-secrets/cosign-stage3-pass.txt`. Every cluster image must be cosign-verified at admission.
- **Linkerd 2.x.** Internal CA + issuer cert generated under `~/sds-secrets/linkerd-*`; mesh injection enforced by OPA admission.
- **Falco.** Runtime threat detection installed alongside the other runtime-security tools.

### 4.2 Stage-3 (substantial progress)

| Sub-stage | Component | Posture |
|---|---|---|
| 3A | Vault HA (Raft, transit auto-unseal) | CLOSED 2026-05-12; v0.3.0-stage-3A-closed tag |
| 3B | SoftHSM v2 (token init + smoke) | Deployed; dev-PIN posture; replaced by HSM appliance in production per ADR-5.1 |
| 3C | OPA Gatekeeper (admission policies) | Installed; baseline policies bundled (`K8sRequireMeshInjection`, `K8sRequireGpuLimit`, `K8sRequireSignedImage`) |
| 3D | Linkerd authorization policies | Audit-mode first; enforce-mode after a Software-Manager-approved window per ADR-3.4 |

Audit recommendation items 1–22 PASS; item 23 closed via the SSH PasswordAuth flip ceremony. See `~/sds-monorepo/docs/stage-gates/stage-3-*.md` for the full evidence trail.

#### Vault HA recovery posture

The Vault HA cluster experienced a Raft regression on 2026-05-18 and is currently in **operator-only recovery** posture per the runbook at `/gov/basic/infra/runbooks/vault-raft-recovery-2026-05-18.md`. The Stage-5 dry-run that follows works around Vault by mounting dev-mode PINs; production cutover must re-establish Vault HA first.

### 4.3 Stage-4 (in flight)

Stage-4 brings up the identity layer. The pre-staged manifests live under `~/sds-monorepo/infra/stage-4/`:

- **Keycloak.** Storage-backend choice per ADR-4.1; version-pin per ADR-4.5; realm-isolation strategy per ADR-4.4 (`rajya-sabha` realm primary; `lok-sabha` realm shell as IAM-infra provisioning only).
- **NIC Parichay.** Federated identity provider for Members and senior staff per ADR-4.2 (token-validation strategy) and ADR-CC-006 (OAuth-acquisition client wiring).
- **FIDO2.** Platform authenticators on Sadasya + cross-device passkeys; attestation-conveyance posture per ADR-4.3.
- **Postgres 16.** Cluster-internal; cluster-PVC-backed; per-Setu logical isolation by schema; init runbook at `docs/runbooks/RUNBOOK-POSTGRES-INIT.md`.

**Parichay credentials posture.** NIC Parichay live credentials are tracked by the Software Manager separately and are not on the critical path for development work. A **dev/staging mock** at `gitlab.sds.local/common-core/fake-parichay` (v0.1.0) provides six Rajya-Sabha-only seed users with the same OAuth surface; swap to real Parichay via the `PARICHAY_BASE_URL` env var when live credentials land. **The mock is dev-only.**

### 4.4 Stage-5 (PKI + eSign substrate; dry-run complete)

The Stage-5 dry-run on 2026-05-18 deployed and verified:

- **SignServer CE** on the k3s substrate (combined dev image for the single-node case; production splits into separate Pods per ADR-5.1).
- **SoftHSM v2** token initialised with `vani-signing-token`.
- **PKCS#11 end-to-end signing path** verified: token init → RSA-2048 keygen → `pkcs11-tool --sign` → `openssl dgst -verify` returns "Verified OK".
- Healthcheck endpoints `http://127.0.0.1:8080/signserver/healthcheck/signserverhealth` and `https://127.0.0.1:8443/...` both return `ALLOK`.

Deviations from production design (all documented):

- Combined SignServer+SoftHSM image (single-node pod-cap constraint).
- Dev PINs in use; rotate to Vault-issued values for non-dev.
- No Linkerd policy on `signing-system` yet; production mirrors the `tijori-system` AuthorizationPolicy convention.
- No C-DAC eSign sandbox client wired yet; pending procurement.

### 4.5 Cross-cutting services (operational)

| Service | Status | Purpose |
|---|---|---|
| **MinIO** | Erasure-coded, deployed | Only object store across the entire programme |
| **OpenSearch + Wazuh** | Deployed | SIEM, audit retention, alerting |
| **TheHive + MISP + Suricata** | Deployed (Wazuh stack) | Incident response and threat-intel |
| **Prometheus + Grafana** | Deployed | Metrics + dashboards (Tijori carries six Grafana dashboards already) |
| **Tempo (OTel backend)** | Deployed | Distributed tracing |
| **OpenTelemetry Collector** | Deployed | Trace + metrics ingest |
| **RabbitMQ** | Deployed (event bus) | Topic-exchange envelope per ADR-CC-002 |
| **Redis 7** | Deployed | Sessions, idempotency, queues |

### 4.6 The audit-Wazuh bridge (Common Core)

A dedicated **audit-bridge** service (`gitlab.sds.local/common-core/audit-bridge` v0.2.0) implements ADR-CC-003. It bridges the programme's RabbitMQ audit-event stream to JSONL (authoritative) and to Wazuh (best-effort). Two-tier hash chaining; replay tool; Prometheus exporter; Stage-5 Kubernetes manifests. Every audit-bearing call across every Setu flows through this bridge.

### 4.7 The DevOps artefact home

Operational discovery, gap analysis, change-log, post-bootstrap state, runbooks and escalation procedures are recorded under `/gov/basic/infra/`:

- `discovery_report.md`
- `gap_analysis.md`
- `changelog.md`
- `state_after_bootstrap.md`
- `runbook.md`
- `escalation.md`
- `runbooks/` — Stage-3 / Stage-4 / Stage-5 operational runbooks

This directory is the single place an on-call operator looks first.

---

## 5. Stage gate evidence (what is signed and tagged)

The Stage-gate artefacts are kept under `~/sds-monorepo/docs/stage-gates/` and each closure is a **GPG-signed git tag** under the Software Manager key.

| Tag | Closure | Date |
|---|---|---|
| `v0.1.0-stage-1-closed` | Stage-1 Bootstrap | (date in STAGE-1-COMPLETE.md) |
| `v0.2.0-stage-2-closed` | Stage-2 Base Software | 2026-05-13 |
| `v0.3.0-stage-3A-closed` | Sub-stage 3A Vault HA | 2026-05-12 |

Stages 3B/3C/3D + Stage 4 + Stage 5 closures are pending. A `STAGE-N-COMPLETE.md` document with an `.asc` ASCII-armoured signature accompanies every closure.

The current stage marker is maintained at `~/sds-monorepo/AGENTS.md` § "Current stage". **Always trust the monorepo AGENTS.md + the latest signed tag; do not rely on stale bootstrap status files.**

---

## 6. Common Core — the shared library family

SDS Common Core is a set of **four sibling repositories** holding cross-Setu shared libraries and contracts. Every Setu consumes one of the three implementation packages; the meta repo holds the parity harness that proves cross-language consistency.

| Repository | Purpose | Consumed by |
|---|---|---|
| `~/sds-common-core-meta/` | Architecture, expected surface, parity manifest, ADRs | (none directly; CI verification only) |
| `~/sds-common-core-python/` | Python 3.12 implementation | Vani Setu (FastAPI); Tijori Setu (FastAPI); any Python tooling |
| `~/sds-common-core-php/` | PHP implementation (Laravel-friendly) | Sanchalan Setu (Laravel 12) |
| `~/sds-common-core-typescript/` | TypeScript implementation (NPM `@sds/common-core`) | Sabha (Next.js); Samagrya (NestJS); Samiti (NestJS); Sadasya backend bits where applicable |

### 6.1 Release status

**Common Core v0.9.0 + v0.9.1 lockstep released** and pushed to GitLab CE at `common-core/{meta,python,php,typescript}` on 2026-05-16. All four repos carry GPG-signed `v0.9.0` and `v0.9.1` tags. **51/51 parity tests pass** at v0.9.1.

- v0.8.0 was feature-complete on the original Section-4.4 module catalogue.
- v0.9.0 added the new `parichay_oauth` module per ADR-CC-006 (the OAuth-acquisition side of the NIC Parichay flow; sibling to v0.4.0 `auth` token-validator).
- v0.9.1 added the `should_refresh` / `shouldRefresh` pre-emptive-refresh helper.

### 6.2 The modules

Common Core covers the cross-cutting concerns that would otherwise be re-implemented in every Setu:

- `auth` — Parichay OAuth token validation (audience-restricted, audience-keyed)
- `parichay_oauth` — Parichay OAuth client (token acquisition + refresh)
- `audit` — Hash-chained, immutable audit events; ADR-CC-001 transport, ADR-CC-003 Wazuh-bridge target
- `events` — RabbitMQ topic-exchange envelope; ADR-CC-002 topology
- `idempotency` — Redis-backed idempotency-key middleware; ADR-CC-005
- `permissions` — Realm-group + audience checks per Setu role catalogue
- `errors` — RFC 7807 problem-json error envelope across every Setu API
- `correlation` — `x-correlation-id` propagation across the mesh
- `observability` — OpenTelemetry / Prometheus / structured logging wiring
- `signatures` — Common Core wrapper around SignServer + Vault Transit signing
- `parity_harness` (meta) — cross-language conformance proofs

### 6.3 ADRs that govern Common Core

| ADR | Topic |
|---|---|
| ADR-CC-001 | Audit transport |
| ADR-CC-002 | Events topology |
| ADR-CC-003 | Audit-Wazuh bridge |
| ADR-CC-004 | AI routing rule (Tijori router policy) |
| ADR-CC-005 | Idempotency |
| ADR-CC-006 | Parichay OAuth client |
| ADR-CC-007 | Cross-namespace AI transport |

### 6.4 What a Setu developer does with Common Core

You **consume** Common Core as a versioned dependency. You **do not** fork it, vendor it, or re-implement any module locally. If a module is missing or insufficient, you open an ADR (CC-NNN) and propose a cross-language addition — the meta repo's parity harness will reject any single-language drift.

---

## 7. Tijori Setu — the AI control plane

Tijori is the **single AI control plane** for SDS. No other Setu loads a model. Every transcription, translation, OCR, embedding, retrieval, and inference request from any other Setu enters Tijori and is routed to the right model.

### 7.1 Status — already shipped through v0.10.0

Tijori Setu is published to GitLab CE at **`gitlab.sds.local/tijori/setu`** (project id 8). The repository carries **10 GPG-signed tags** from `v0.1.0-draft.1` through `v0.10.0`, with **253+ tests** passing on every push (ruff → mypy → pytest). The build is Python 3.12 + FastAPI 0.115 + Pydantic 2.7.

| Slice | Scope |
|---|---|
| 0.1.0 | App skeleton, middleware (Parichay JWT, correlation, idempotency, error envelope), translate API surface, in-cluster IndicTrans2 stub, Bhashini stub, audit-emit hook, in-memory job store |
| 0.2.0 | Two-endpoint transcribe family (`/v1/asr/onprem`, `/v1/asr/sarvam`); real Whisper-large-v3 client; real Saarika v2 client (paid Sarvam API, scope-gated, per-tenant INR cap); real Bhashini translation; Vault-backed secrets; egress allow-list verified at startup |
| 0.3.0 | Retrieval API (`/v1/retrieval/search`, `/v1/retrieval/collections`); Qdrant async hybrid client (dense + sparse) with claims-driven access-class filter; BGE-M3 embeddings; BAAI reranker-v2-m3 |
| 0.4.0 | Inference API (`/v1/inference`, prompt-template registry); vLLM-served Qwen 2.5 7B Instruct (in-cluster, open-weight); citation-required guardrail |
| 0.5.0 | OCR API (`/v1/ocr`, `/v1/ocr/results/{id}`); layered router Tesseract 5 → PaddleOCR → VLM fallback; confidence-floor escalation |
| 0.6.0 | Document-ingestion pipeline; event envelope + InMemoryEventPublisher + InMemoryEventBus; OcrConsumer; bulk-ingest CLI |
| 0.7.0 | Programme-wide immutable audit store: SHA-256 hash chain + WORM sink; no-training guardrail at startup (scans every model-client config for forbidden flags and halts) |
| 0.8.0 | Dev-env readiness + Stage-5 prep: MinIO async client + signed-URL fetcher; real RabbitMQ event publisher (aio-pika); Postgres job store (asyncpg); Redis-backed budget tracker |
| 0.9.0 | BM25 sparse retrieval leg integrated with Qdrant dense leg; latency budget enforcement; single reformat retry on inference output-validation failure; per-region OCR language detection; OpenTelemetry + Prometheus observability; `dev/{docker-compose.yml,up.sh,down.sh,README.md}` quickstart |
| 0.10.0 | **Production wire-up + operational maturity.** `wiring/{production,dev}.py` composition root; Helm chart at `helm/tijori-setu/` (14 files; `helm lint` clean; Vault Agent inject; Linkerd inject; NetworkPolicy; HPA + PDB); **six Grafana dashboards**; standalone Python SDK (`sdk/tijori-sdk-python/`); 980-line Stage-5 operator runbook; per-tenant token-bucket rate-limit middleware |

### 7.2 Tijori container topology — production posture

Tijori lives in its **own namespace `tijori-system`** on the GPU node pool. The GPU pool is tainted `nvidia.com/gpu=present:NoSchedule` and only Tijori GPU pods tolerate the taint.

| Workload | Kind | Replicas (min/desired/max) | GPU | Node pool |
|---|---|---|---|---|
| `tijori-router` | Deployment | 2 / 3 / 6 | No | CPU |
| `tijori-inference` (Triton) | Deployment | 1 / 2 / 4 (HPA) | Yes (1 per replica) | GPU |
| `tijori-llm` (vLLM) | Deployment | 1 / 1 / 2 (HPA) | Yes (1 or 2 per replica) | GPU |
| `tijori-models-init` | Job (one-shot per model bump) | n/a | No | CPU |
| `kong-tijori-egress` | Deployment | 2 / 2 / 4 | No | CPU |

The `tijori-router` is CPU-only and I/O-bound. The `tijori-inference` (Triton) and `tijori-llm` (vLLM) workloads consume GPU. `kong-tijori-egress` is the **only** egress path to `api.sarvam.ai`, with TLS pinning, host-allow-list, rate-limit plugin, and a custom Lua `budget-guard` plugin that rejects 429 once the monthly INR cap is exceeded.

### 7.3 The GPU requirement — four NVIDIA L40S cards for Tijori production

Tijori Setu production posture **requires four NVIDIA L40S cards (48 GiB VRAM each)** on the GPU node pool:

| Card | Workload | Reason |
|---|---|---|
| **Card 1** | `tijori-inference` replica 1 (Triton) | Whisper-large-v3 + IndicTrans2 + IndicTTS + pyannote + Indic-BERT co-resident with headroom for dynamic-batch peaks |
| **Card 2** | `tijori-inference` replica 2 (Triton) | HPA scale-out to absorb burst load; pod-anti-affinity keeps replicas on separate nodes |
| **Card 3** | `tijori-llm` replica 1 (vLLM) | Sarvam-M FP16 (24 B params, ~48 GiB); single card sufficient for the default in-cluster open-weight model |
| **Card 4** | `tijori-llm` replica 2 OR tensor-parallel partner | Either HPA scale-out for vLLM, OR tensor-parallel=2 for Mixtral-8x7b FP16 when documented quality benchmarks demand it; AWQ-4bit Mixtral fits on a single card (~25 GiB) and is the default |

L40S is mandatory because:

- **L4 / A10G cannot fit Sarvam-M FP16** (Vani master-note's earlier L4/A10G recommendation is superseded by Tijori's master-note § 5).
- **48 GiB VRAM** ceiling accommodates the multi-model co-residency posture without VRAM swapping.
- **MIG is disabled in v1** (not validated on L40S for the SDS workload mix).
- **Pod-anti-affinity** prevents two replicas of the same Deployment landing on the same node.

A **third prod L40S** is held in the Master Plan v2.2 contingency budget; lifting the GPU ceiling from 4 to 6 cards is the planned scale-out lever before Monsoon 2027 if budget headroom permits.

### 7.4 The Sarvam policy — paid-API discipline

**Sarvam Saarika v2 is the ONLY Sarvam product Tijori consumes.** This is a Software Manager directive (2026-05-15) and is binding programme-wide:

- No Saaras, no Mayura, no Bulbul, no Sarvam-M.
- The Saarika v2 path is gated by the `tijori.asr.sarvam` permission scope on the Parichay JWT.
- A **per-tenant daily + monthly INR budget** is enforced; an **80% warn alert** fires before hard-stop.
- All other AI (translation, inference, OCR, retrieval, embeddings) runs **in-cluster open-weight**.
- **Sabha Setu is on-prem only.**

The model-client interfaces are **closed** at `[sarvam, bhashini, in_cluster_open_weight]`. Adding a destination is a Section-9.7.29 prohibition: it requires an ADR and Architect approval. The no-training guardrail at Tijori startup scans every registered client config for `share_for_training`, `enable_telemetry`, `feedback_to_provider`, `opt_in_to_model_improvement`, and `data_retention_for_improvement`; a violation **halts startup** in strict mode (default).

### 7.5 Tijori ADRs

| ADR | Topic |
|---|---|
| ADR-TIJORI-1 | Creation of Tijori Setu (Conflict 1 resolution; per-Setu repo placement) |
| ADR-TIJORI-2 | API catalogue scope — operative contracts in `gov/basic/sds-contracts/tijori/`, governance pack is non-normative |
| ADR-5.1 | SignServer / Tijori scope split |
| ADR-CC-004 | AI routing rule (Tijori router policy) |
| ADR-CC-007 | Cross-namespace AI transport |

---

## 8. Design Identity — binding on every UI surface

Every SDS surface (UI, icon, deck, splash screen, document template, comms asset, operator dashboard, container label, OpenAPI tag) **must** conform to the **SDS Design Identity Document (March 2026, Rajya Sabha Secretariat)** and to the per-Setu design pack.

### 8.1 Canonical sources

| Artefact | Status |
|---|---|
| `docs/design/SDS-Design-Identity-Document.pdf` | **Binding written charter** (March 2026) |
| `docs/design/master-handoff/` | **Binding visual reference** (extracted from `SDS_designs_master.zip`, sha256 verified) |
| `docs/design/master-tokens.json` | **Upstream extract** in W3C Design Tokens format; single source for every per-Setu pack |
| `docs/design/master-tokens.css` | Programmatic CSS variable export; any web surface may `@import` it |
| `docs/design/family-portrait.svg` | Six-Setu family portrait |
| `docs/design/review-gallery.md` | 18 PNG renders + family portrait for at-a-glance Architect review |

### 8.2 The five binding design principles

1. **One idea per icon.** Every Setu's icon expresses one concept, not a feature catalogue.
2. **White on colour, maximum contrast.** Pure white shapes on the Setu colour. No gradients, no shadows, no glassmorphism.
3. **Progressive disclosure at three scales.** 96 px shows every detail; 64 px drops secondary elements; 44 px retains only the irreducible core. If something does not survive at 44 px, it was decoration. Remove it.
4. **Six colours, one family.** The six Setu colours sit together as one palette. Never repaint a Setu in another's colour, even on a shared surface.
5. **Hindi first.** Every label: Devanagari first, English transliteration second, functional descriptor third. The Council of States honours its institutional script.

### 8.3 Naming hierarchy on every label

1. **Devanagari first** (e.g. सदस्य सेतु)
2. **English transliteration second** (e.g. Sadasya Setu)
3. **Functional descriptor third** (e.g. "Members' digital companion")

App launchers and ultra-compact surfaces may use position 1 only. Documentation, decks, and splash screens use all three.

### 8.4 Per-Setu design pack pattern

Each Setu owns its pack at `docs/setus/<setu>-setu/design/` containing:

- `tokens.json` — Setu-local token overrides; pointer to `master-tokens.json`
- `tokens.css` — CSS variable projection
- `brand.md` — Setu colour rationale + icon construction + naming hierarchy
- `theme-stub.md` — Stage-6 consumer guidance for the Setu's framework stack
- `icon/<setu>-setu-{44,64,96}.svg` — three icon scales
- `README.md` — pack index
- `CONFORMANCE.md` (where applicable) — realignment record

The per-Setu pack pattern is adopted by **ADR-DESIGN-001**. Conflicts between a pack and the canonical PDF resolve in favour of the PDF.

### 8.5 Hard rules for any developer touching UI

- **Do not** inline hex values in source code. Pull from `master-tokens.json` or the per-Setu `tokens.json` via the CSS variables.
- **Do not** tint, shade, or recolour a Setu colour. Hover and pressed states use opacity on the same hex, never a darker variant.
- **Do not** introduce a dark-mode variant for any Setu. The identity is fixed.
- **Do not** modify files under `master-handoff/source/` or `master-handoff/SDS_designs_master.zip`. They are byte-for-byte canonical.
- **Do not** propose a cross-Setu repaint. Escalate via an ADR.

### 8.6 Pack status snapshot

| Setu | Pack | Status | Aligned to master tokens? |
|---|---|---|---|
| Sadasya Setu | `docs/setus/sadasya-setu/design/` | DRAFT v1.0.0-draft.1 | Pending realignment |
| Sanchalan Setu | `docs/setus/sanchalan-setu/design/` | DRAFT v1.0.0-draft.1 | Pending realignment |
| Vani Setu | `docs/setus/vani-setu/design/` | v1.1.0-draft.1 (2026-05-18) | **Yes** (see `CONFORMANCE.md`) |
| Sabha Setu | `docs/setus/sabha-setu/design/` | DRAFT v1.0.0-draft.1 | Pending realignment |
| Samagrya Setu | `docs/setus/samagrya-setu/design/` | DRAFT v1.0.0-draft.1 | Pending realignment |
| Samiti Setu | `docs/setus/samiti-setu/design/` | DRAFT v1.0.0-draft.1 | Pending realignment |
| Tijori Setu | `docs/setus/tijori-setu/design/` | DRAFT v1.0.0-draft.1 | Pending public-edge alignment for `tijorisetu.rajyasabha.digital` |

---

## 9. Functional features — what each Setu does

A short feature reference. The full per-Setu specification lives in each Setu's `master-note.md` (see § 14 reading order).

### 9.1 Sanchalan Setu (apex orchestrator)

ERP-grade workflow engine for the Rajya Sabha Secretariat. Owns:

- File noting and movement (administrative files; legislative files; assembly files).
- Agenda preparation for sittings and committees.
- Claims, payroll, attendance, gate-pass, leave management for Members and staff.
- Roster coordination across the Secretariat departments.
- Administrative clearance workflows; dispatches to the other Setus.

Stack: **Laravel 12 + Vue 3 + Vite + PostgreSQL 16 + Redis 7.**

### 9.2 Sabha Setu (public portal + chamber backend)

Two-faced Setu:

- **Public portal** at `sabha.rajyasabha.digital` — Next.js 15; public-facing read-only content (questions, notices, debates, publications); cache-window posture under review.
- **Chamber backend** — NestJS 10 + PostgreSQL; during a sitting, hosts proceedings, voting, broadcast coordination, and the live document feed for the floor.

Submissions surface covers **21 device types** (Members' submission devices and Secretariat input channels). LoB / MoB / minute-book lifecycle is Sabha-owned.

### 9.3 Samiti Setu (secure committee intelligence)

The **most confidentiality-sensitive Setu** in the programme. Supports the Department-Related Parliamentary Standing Committees (DRPSCs) of the Rajya Sabha. Owns:

- Real-time document access during committee sittings (the "live document feed").
- Encrypted member-to-member communications within a committee (Olm/Megolm end-to-end encryption; Samiti server cannot decrypt).
- Confidential briefing-material distribution (read-only with screenshot suppression).
- Witness deposition management (scheduling, in-camera framing, redaction policy).
- Pre-publication recommendation drafting and member sign-off (Phase-2).

Stack: **NestJS 10 + PostgreSQL + Redis 7 (hardened).**

Confidentiality posture is **tighter than any other Setu**:

- FIDO2 + step-up to cross-device passkey for confidential reads.
- Platform-level screenshot suppression (Android `FLAG_SECURE`, iOS `screenCaptureProtection`) on every member-facing surface.
- **4-eyes declassification** (no single staff member can declassify a document; two-staff approval required).
- **Article 105 protection** for member-to-member chat content — Samiti backend deliberately cannot decrypt; audit logs the fact of communication, not the content.
- 24 DRPSCs + standing committees populated; ~245 Members; ~60–80 Committee Branch staff; variable witness population.

### 9.4 Sadasya Setu (members' digital companion)

Members' mobile companion: attendance, voting, document feed, MP-services workflows.

Stack: **Flutter 3.24 + Kotlin/Swift native modules.**

Hard privacy posture:

- **Never** capture IMEI, SIM serial, ANDROID_ID, advertising ID, or any other long-lived hardware identifier.
- **Never** read the phone book, SMS, call log, photo library (beyond a user-initiated single-shot for Phase 2 voting), or microphone.
- **Every** runtime permission carries a DPDP-compliant purpose narration in Hindi + English shown to the user before the OS prompt.
- AES-256-GCM at rest; Ed25519 / ECDSA P-256 for signatures; PKCE-S256 only for OAuth; no SHA-1, no MD5, no RC4, 3DES, or Blowfish.

### 9.5 Vani Setu (AI-powered language bridge)

**GPU container, separate from every other Setu.** Owns:

- Live transcription of Rajya Sabha proceedings (IndicWhisper + Whisper-large-v3 fine-tuned on Sansad-TV).
- Real-time translation across the 22 scheduled languages (IndicTrans2 1B + 200M).
- Diarisation (pyannote).
- TTS for accessibility (IndicTTS + Coqui XTTS-v2).
- Embedding service for retrieval (Indic-BERT, BGE-M3).
- Committee-track transcription (Track-B at v1.5; consumed by Samiti).

Stack: **FastAPI + Triton (inference) + vLLM (LLM); Vue 3 operator console.**

Vani is **first to land** in Stage-6 — the Vani-Reporter Workspace is the first piece of Setu application code authorised under Stage-6.

**Live URL.** Vani Setu is reachable in the browser at **https://vanisetu.rajyasabha.digital**. This is the first publicly-resolvable SDS Setu surface; use it for daily smoke-checks and to give incoming developers an immediate visual reference for the design identity, the Hindi-first naming hierarchy, and the warm-coral `#A23A1F` palette in production.

### 9.6 Samagrya Setu (ministry meets parliament)

Bidirectional fabric between Union Ministries and the Rajya Sabha Secretariat. Owns:

- **148 document categories** (Bills, Amendments, Papers to be Laid, ATRs, Question replies, Statements, etc.).
- LARDIS (legacy Lok Sabha and Rajya Sabha Document Indexing System) **replacement**.
- Mayan EDMS-backed document storage and lifecycle.
- Inter-ministry workflow for paper-laying ceremonies.

Stack: **NestJS 10 + PostgreSQL 16 + Mayan EDMS.**

### 9.7 Tijori Setu (AI control plane)

Already covered in § 7. Functional surface as published in v0.10.0:

- `/v1/translate`, `/v1/translate/jobs`, `/v1/translate/jobs/{id}`
- `/v1/asr/onprem`, `/v1/asr/sarvam`
- `/v1/retrieval/search`, `/v1/retrieval/collections`
- `/v1/inference`, `/v1/prompt-templates/{name}/versions/{version}`
- `/v1/ocr`, `/v1/ocr/results/{result_id}`
- `/v1/health/{live,ready}` (per-dep probes; contract-shaped response)
- Six Grafana dashboards: overview / GPU / Sarvam budget / hash-chain audit / OCR layered / retrieval hybrid

**Live URL.** Tijori Setu is served at **https://tijorisetu.rajyasabha.digital** for the public browser/API edge. This URL is for operator access, smoke-checks, and externally reachable documentation links. Internal SDS services must not call this public hostname directly; they must use the configured in-cluster endpoint for the `tijori-router` service and pick it up from environment/configuration.

---

## 10. Setu allocation — who builds what

The Software Manager has set the following allocation of remaining Setu implementation work:

### 10.1 Setus already substantially delivered

| Setu | Posture |
|---|---|
| **Tijori Setu** | Already shipped to v0.10.0 with Helm + dashboards + SDK + operator runbook. **Continues under the in-house engineering line** — production wire-up, the standalone TypeScript SDK, GPU node-pool provisioning, and Sarvam-budget-gate hardening remain on the in-house roadmap. |
| **Common Core (four sibling repos)** | Already shipped to v0.9.1 with 51/51 parity tests green. **Continues under the in-house engineering line** — module additions land under the same ADR-CC-NNN process and the meta repo's parity harness gates any cross-language drift. |
| **audit-bridge, fake-Parichay** | Already shipped. Maintained in-house. |

### 10.2 Setus for the incoming developer team

The Software Manager's direction is:

- **Samiti Setu** is the **primary Setu for the incoming developer team.** Its NestJS-pinned stack, hardened confidentiality posture, and committee-intelligence surface make it a well-bounded handover scope.
- **Sanchalan Setu** is the **second candidate Setu** for the incoming developer team. Its Laravel 12 + Vue 3 + Vite stack matches the toolchain (Laravel + UV) the team is being on-boarded with; the apex-orchestrator scope is bounded by the agreed Phase-1 surface.
- **Additional Setus may be added** to the incoming team's allocation at the Software Manager's discretion.

### 10.3 Setus continuing under the in-house engineering line

- **Sabha Setu** (Next.js 15 + NestJS 10; public portal + chamber backend)
- **Vani Setu** (FastAPI + Triton + vLLM; GPU container; Stage-6-first)
- **Samagrya Setu** (NestJS 10 + Mayan EDMS; ministry interaction + LARDIS replacement)
- **Sadasya Setu** (Flutter; mobile companion)
- **Tijori Setu** continuation (above)
- **Common Core** continuation (above)

### 10.4 The cross-cutting deliverables every team consumes

Regardless of allocation, every team — including the incoming developer team — **consumes** rather than implements:

- **Common Core** in their target language (PHP for Laravel, TypeScript for NestJS, Python for FastAPI).
- **Tijori Setu** as the single AI router. No Setu loads a model. Every translation, transcription, OCR, retrieval, or inference call goes to Tijori.
- **Stage-3 / Stage-4 / Stage-5 substrate**: Vault for secrets, Keycloak for IAM, Linkerd for mTLS, OPA for admission, MinIO for object storage, SignServer for cryptographic signing.
- **Design Identity** — both the canonical PDF and the per-Setu design pack are binding on every UI surface.

---

## 11. Developer environment setup

This section is the **install runbook** for an incoming developer.

### 11.1 Workstation prerequisites

| Tool | Version | Reason |
|---|---|---|
| OS | Ubuntu 22.04 LTS (preferred) or macOS 14+ | Matches dev-host posture |
| Git | 2.40+ | GPG-signed commits required |
| GPG | 2.2+ | Software Manager and developer keys; signed commits enforced |
| Docker / containerd | latest stable | Local k3d cluster + Tijori dev-stack |
| Node.js | 22 LTS | NestJS 10 + Next.js 15 + TypeScript Common Core |
| pnpm or npm | latest | TypeScript dependency management |
| PHP | 8.3+ | Laravel 12 (Sanchalan) |
| Composer | 2.7+ | PHP dependencies |
| Python | 3.12 (3.11 floor) | FastAPI / Tijori / Vani / Common Core Python |
| UV (Astral) | latest | **The Python toolchain pin** — replaces pip / virtualenv / pyenv for SDS Python work |
| Flutter | 3.24 stable | Sadasya Setu |
| Helm | 3.14+ | Kubernetes deploys |
| kubectl | matching k3s 1.29.x | Cluster ops |
| Linkerd CLI | matching cluster | Mesh ops |
| cosign | 2.x | Image signature verification |

### 11.2 Get cluster access

Cluster credentials and the internal CA bundle are issued by the Software Manager:

1. Internal CA at `~/sds-secrets/sds-internal-root-ca.crt` — install in your `~/.local/share/ca-certificates/` and **scope to user (not system) trust** as a precaution against accidental cross-environment trust.
2. GitLab personal-access-token (PAT) issued from `gitlab.sds.local`. Configure git with user-scope `credential.helper store` and the PAT.
3. K8s `KUBECONFIG` for the dev cluster (read-write to your assigned namespace; read-only to substrate namespaces).

### 11.3 Clone the working tree

```bash
mkdir -p ~/sds-dev && cd ~/sds-dev
git clone https://gitlab.sds.local/sds/sds-monorepo.git
git clone https://gitlab.sds.local/common-core/python.git       sds-common-core-python
git clone https://gitlab.sds.local/common-core/php.git          sds-common-core-php
git clone https://gitlab.sds.local/common-core/typescript.git   sds-common-core-typescript
git clone https://gitlab.sds.local/common-core/meta.git         sds-common-core-meta
git clone https://gitlab.sds.local/tijori/setu.git              sds-tijori-setu
git clone https://gitlab.sds.local/common-core/audit-bridge.git sds-audit-bridge
git clone https://gitlab.sds.local/common-core/fake-parichay.git sds-fake-parichay
```

### 11.4 Per-Setu local development quickstart

For each Setu you build, the standard quickstart is:

#### Sanchalan (Laravel 12 + Vue 3 + Vite)

```bash
cd ~/sds-dev/sanchalan-setu
cp .env.example .env
docker compose -f dev/docker-compose.yml up -d   # Postgres 16, Redis 7, MinIO, Keycloak, fake-Parichay
docker compose exec app composer install
docker compose exec frontend npm install
docker compose exec app php artisan key:generate
docker compose exec app php artisan migrate
docker compose up -d web app frontend
```

#### Samiti (NestJS 10 + Postgres + Redis)

```bash
cd ~/sds-dev/samiti-setu
pnpm install
cp .env.example .env
docker compose -f dev/docker-compose.yml up -d   # Postgres 16, Redis 7, RabbitMQ, fake-Parichay, Keycloak
pnpm run migrate:dev
pnpm run start:dev
```

#### Tijori (Python 3.12 + FastAPI + UV)

```bash
cd ~/sds-dev/sds-tijori-setu
uv venv && source .venv/bin/activate
uv pip install -e ".[dev,inference]"
bash dev/up.sh   # Postgres / Redis / Qdrant / MinIO / RabbitMQ / Keycloak / Vault / Jaeger
docker compose exec tijori pytest
docker compose up -d tijori
```

#### Common Core (consumed, not cloned for direct dev unless adding a module)

```bash
# Python (UV)
uv pip install sds-common-core==0.9.1

# PHP (Composer)
composer require sds/common-core:^0.9.1

# TypeScript (npm scope @sds)
pnpm add @sds/common-core@^0.9.1
```

### 11.5 The Tijori dev quickstart stack

The Tijori `dev/docker-compose.yml` is the **reference dev stack** for any Python Setu. It brings up, pinned and health-checked, on localhost only:

- Postgres 16 (job store, idempotency, audit metadata)
- Redis 7 (sessions, budget tracker, rate-limit)
- Qdrant (vector retrieval)
- MinIO (object storage for inputs/outputs)
- RabbitMQ (event bus per ADR-CC-002)
- Keycloak (IAM)
- Vault (secrets)
- Jaeger (OpenTelemetry traces)

You can reuse this docker-compose as the scaffold for Sanchalan / Samiti local development (substitute Mayan EDMS for Sanchalan's document-storage testing if needed).

### 11.6 IDE / tooling configuration

- **Linting and formatting.** `make lint` runs across the monorepo (eslint, prettier, ruff, pint).
- **Testing.** `make test` runs the union of phpunit, jest, flutter test, pytest.
- **Security.** `make security` runs trivy fs, semgrep, gitleaks.
- **SBOM.** `make sbom` runs cyclonedx-cli and uploads to Dependency-Track.
- **Acceptance.** `make accept` runs the acceptance-test harness against the AT catalogue.

### 11.7 Commit and contribution discipline

| Rule | Source |
|---|---|
| GPG-signed commits, **every** commit | Software Manager mandate |
| **Conventional Commits** with the FR / NFR / SR id, e.g. `feat(FR-SAMITI-PHASE1): add live document feed` | Root `AGENTS.md` § Conventions |
| **One concern per PR; one PR per FR id** | Same |
| **British-Indian spelling** in user-facing strings (organise, recognise, harmonise) | Same |
| **No em dashes** in code comments or strings | Same |
| **No new dependency** without an SBOM line in the commit body + Architect approval + licence-compatibility check (Apache 2.0 / MIT / BSD-3 / LGPL-with-exception only; no GPL-only) | Same |
| **ADR + README** updated for every architectural change | Same |
| **Acceptance test (AT-id)** for every functional change | Per-Setu AT catalogue |

### 11.8 Hard rules — never violate

- **NEVER** read or write `.env*`, `secrets/`, `*.pem`, `*.p12`, `*.jks`. Route secrets via Vault.
- **NEVER** call non-allow-listed external APIs.
- **NEVER** write to production directly.
- **NEVER** implement silent SMS, covert tracking, or any interception primitive.
- **NEVER** rely on IMEI or SIM-serial for device binding.
- **NEVER** use MD5, SHA-1, RC4, 3DES, or Blowfish.
- **NEVER** begin work that belongs to a later stage.

---

## 12. Production requirements

These are the **non-negotiable** production prerequisites the platform must satisfy before Monsoon Session 2027 go-live.

### 12.1 Cluster posture

- **3-node control plane** + dedicated **GPU worker nodes** for `tijori-system` and `vani-system`.
- **4 NVIDIA L40S 48 GiB** GPUs minimum on the GPU pool, allocated per Tijori master-note § 7.3 above. A 5th and 6th card form the Master-Plan-v2.2 contingency budget.
- **Pod-anti-affinity** between GPU replicas; **GPU pool taint** `nvidia.com/gpu=present:NoSchedule`.
- **NIC NGC hosting** only. No data leaves the sovereign cloud. No AWS / Azure / GCS object storage. **MinIO erasure-coded** for all object storage.

### 12.2 Cryptographic substrate

- **Vault HA (Raft)** + **transit auto-unseal** healthy. Vault HA recovery (per `/gov/basic/infra/runbooks/vault-raft-recovery-2026-05-18.md`) is a Stage-3-closure precondition.
- **HSM appliance** (replacing SoftHSM v2) on the production signing path per ADR-5.1.
- **C-DAC eSign API sandbox account** procured and wired into the Common Core `signatures` module. (Stage-5 follow-up.)
- **Cosign image signing** verified on admission.
- **Vault-issued PINs** replacing all dev PINs.
- **SignServer GenericSigner worker** configured via admin CLI, referencing the HSM-issued token; smoke `POST /signserver/process` from inside the cluster.

### 12.3 Identity

- **NIC Parichay live credentials** wired into the `parichay_oauth` Common Core module (replacing `fake-parichay`). This is tracked by the Software Manager separately and is not on the critical path for development.
- **Keycloak `rajya-sabha` realm** populated with the per-Setu groups (`samiti-member`, `samiti-committee-branch-staff`, `samiti-witness`, `samiti-super-admin`, etc. — and the matching realm-group catalogues for every other Setu).
- **FIDO2 enrolment** complete for Committee Branch staff and Members on Sadasya.
- **Audience-restricted bearer tokens**: every Setu's service client is `svc-<setu>` and OPA rejects audience mismatches.

### 12.4 Network and mesh

- **Linkerd mTLS** enforce-mode (not audit-mode) on every production namespace, per ADR-3.4.
- **AuthorizationPolicy** on `tijori-system`, `vani-system`, `signing-system` restricting ingress to the allowed consumer service accounts.
- **OPA Gatekeeper** policies enforced: `K8sRequireMeshInjection`, `K8sRequireGpuLimit`, `K8sRequireSignedImage`, `K8sBlockSarvamApiOutsideTijori`.
- **Kong egress** the **only** path to `api.sarvam.ai`; TLS-pinned; rate-limited; budget-guarded.

### 12.5 Observability and SIEM

- **Prometheus + Grafana** dashboards green for every Setu (Tijori already has six dashboards in `dashboards/tijori/`; the rest of the Setus land their dashboards as part of Stage-6).
- **OpenTelemetry traces** at the OTel Collector → Tempo backend → Grafana visualisation.
- **JSON structured logs** to OpenSearch via Fluent Bit; per-Setu index `sds-<setu>-logs-*`.
- **Wazuh rules** per Setu (Tijori-200001 through Tijori-200010 as the model; analogous Wazuh-rule blocks per Setu).
- **Audit-Wazuh bridge** healthy; JSONL authoritative; Wazuh best-effort; hash-chain replay tool available.

### 12.6 Backup and DR

- **MinIO erasure-coded** with geographic replication inside NIC NGC.
- **Vault Raft snapshots** to MinIO; tested restore procedure.
- **Postgres logical + physical backups** with PITR.
- **Per-PVC retention** (e.g. Tijori 7-day model-PVC retention for rollback).

### 12.7 Compliance

- **DPDP Act 2023** posture per-Setu, with explicit consent for citizen data principals (especially Samiti witnesses); per-Setu DPDP impact assessment before every Phase increment.
- **Public Records Act 1993** retention (typically 7 years for parliamentary records).
- **IT Act 2000** posture for digital signatures (via the C-DAC eSign + SignServer chain).
- **Telegraph Act / Cyber Security**: no interception primitives anywhere in code.

---

## 13. Pending gaps — what is **not** yet closed

This section is the punch-list of work that must complete between now and the production cutover.

### 13.1 Substrate gaps

| Gap | Stage | Status |
|---|---|---|
| Vault HA Raft recovery on the dev cluster | 3A | OPEN; runbook in place; operator-only recovery |
| Stage-3B SoftHSM production posture (replace with HSM appliance, Vault-issued PINs) | 3B | OPEN (deferred to Stage-5+) |
| Stage-3C OPA bundle full enforce-mode | 3C | OPEN |
| Stage-3D Linkerd audit-mode → enforce-mode flip ceremony | 3D | OPEN |
| Stage-3 closure tag (`v0.3.0-stage-3-closed`) | 3 | NOT YET ISSUED |

### 13.2 IAM gaps (Stage-4)

| Gap | Status |
|---|---|
| Keycloak `rajya-sabha` realm full population (every Setu's realm groups + service clients) | OPEN |
| NIC Parichay live-credentials wiring (currently fake-Parichay in dev) | OPEN; tracked by SM separately |
| FIDO2 enrolment ceremony for Committee Branch staff and Members | OPEN |
| ADR-CC-006 Parichay OAuth client — production wire-up | ACCEPTED 2026-05-16; production wire-up OPEN |
| Stage-4 closure tag (`v0.4.0-stage-4-closed`) | NOT YET ISSUED |

### 13.3 PKI and eSign gaps (Stage-5)

| Gap | Status |
|---|---|
| Split-Pod SignServer + HSM appliance (production split, not the combined dev image) | OPEN |
| Vault PKI engine for SignServer admin / HTTPS truststore | OPEN |
| Vault-issued PINs replacing dev PINs | OPEN |
| SignServer GenericSigner worker configured | OPEN |
| C-DAC eSign sandbox account procurement + wiring | OPEN |
| Linkerd AuthorizationPolicy on `signing-system` | OPEN |
| OQ-5.1 disposition (SignServer eSign + CCA-API scope) ratified by Architect | OPEN |
| Stage-5 closure | NOT YET ISSUED |

### 13.4 Setu application gaps

| Setu | Posture | Notable gaps |
|---|---|---|
| Tijori Setu | v0.10.0 shipped | Production GPU node pool not yet provisioned; SDK for TypeScript / PHP pending; OPA `policies/` bundle pending; final Architect ratification of every container-architecture |
| Vani Setu | services tree present; Stage-6-first | 12 entry gates (4 CLOSED, 10 OPEN as of 2026-05-16); 13-step entry-verification checklist |
| Sanchalan Setu | governance pack complete; AT catalogue 11 ATs | All application code is Stage-6 work; preconditions tracker not yet authored |
| Samiti Setu | governance pack complete (master-note v0.1, container-arch v0.1, OpenAPI v0.1, design pack v1.0.0-draft.1, AT catalogue 12 ATs, policies/ bundle); twelve open questions for the Architect | All application code is Stage-6 work; the most-sensitive confidentiality posture in the programme — substantial Architect revision required before code lands |
| Sadasya Setu | governance pack present; AT catalogue 11 ATs | All application code is Stage-6 work |
| Sabha Setu | governance pack complete; AT catalogue 10 ATs | All application code is Stage-6 work |
| Samagrya Setu | governance pack complete; AT catalogue 10 ATs | All application code is Stage-6 work; LARDIS migration design |

### 13.5 Cross-cutting gaps

- **Design Identity master-tokens realignment** for Sadasya / Sanchalan / Sabha / Samagrya / Samiti packs (Vani already realigned 2026-05-18; rest pending).
- **Per-Setu preconditions trackers** for Sadasya / Sanchalan / Sabha / Samagrya / Samiti (Vani's and Tijori's already authored).
- **Stage-6 master spec** completion for Setus beyond Vani-first.
- **Stage-7 and Stage-8** not yet speced.
- **Tijori OPA `policies/` bundle** (every other Setu has its `policies/` directory; Tijori's is pending and is Tier-3 / future work).
- **Architect ratification** of every `container-architecture.md` (all currently DRAFT v0.1; resource shapes are explicit placeholders).

### 13.6 Open questions roll-up (across Setus)

| Bucket | Where to look |
|---|---|
| Vani — model selection, retention classes, correction window, etc. (8 OQs) | `docs/setus/vani-setu/master-note.md` § 13 |
| Sadasya — audience tiering, attendance design, MDM posture | `docs/setus/sadasya-setu/master-note.md` § 16 |
| Sabha — public-portal scope, cache-window posture | `docs/setus/sabha-setu/master-note.md` § 14 |
| Sanchalan — Laravel Octane vs Reverb, audit-DB design, state-machine vs event-sourced (11 OQs) | `docs/setus/sanchalan-setu/master-note.md` § 8 |
| Samagrya — UI framework, Mayan version, LARDIS integration (10 OQs; OQ-SAMAGRYA-7 already resolved) | `docs/setus/samagrya-setu/master-note.md` § 8 |
| Samiti — witness device-code flow, chat protocol, right of erasure (12 OQs) | `docs/setus/samiti-setu/master-note.md` § 8 |
| Tijori — routing rules, Sarvam budget, model catalogue | `docs/setus/tijori-setu/master-note.md` |

---

## 14. Reading order for a new contributor

After you finish this document, read in this order:

1. **`~/sds-monorepo/AGENTS.md`** — operational rulebook; hard rules; current stage marker.
2. **`~/sds-monorepo/docs/master-frame.md`** — programme architectural index.
3. **The current stage's master spec** (e.g. `docs/specs/stage-3-master-spec.md` while Stage-3 is active; `stage-4-master-spec.md`, `stage-5-master-spec.md`, `stage-6-master-spec.md` ahead).
4. **The Setu master-note for your allocation** (e.g. `docs/setus/samiti-setu/master-note.md` if you are picking up Samiti).
5. **The Setu container-architecture** and **API contracts** for your allocation.
6. **The Design Identity Document** (`docs/design/SDS-Design-Identity-Document.pdf`) plus **`docs/design/master-handoff/README.md`** plus your Setu's `design/` pack — mandatory for any UI work.
7. **The ADR index** at `docs/adr/` — read every ADR your Setu's master-note references.
8. **`docs/architecture/setu-contracts.md`** — binding cross-Setu service contract; mandatory if your work crosses Setu boundaries.
9. **`docs/architecture/iam-consumer-matrix.md`** — canonical Keycloak-client table.
10. **`docs/architecture/setu-governance-pack-inventory.md`** — at-a-glance gap map for the eight artefact types tracked per Setu.

### 14.1 The ADR ledger (24 ADRs)

| Group | Range | Topic |
|---|---|---|
| Stage-3 substrate | ADR-3.1 — ADR-3.5 | Vault keyshare custody, PKI / HSM mount timing, Vault-OPA proxy decision, Linkerd policy phasing, GPG rekey |
| Stage-4 IAM | ADR-4.1 — ADR-4.5 | Keycloak storage backend, Parichay token validation, FIDO2 attestation conveyance, realm-isolation strategy, Keycloak version pin |
| Stage-5 PKI | ADR-5.1 | SignServer / Tijori scope split |
| Stage-6 Setus | ADR-6.1, ADR-6.3 | Vani services monorepo placement, pipeline class drift |
| Common Core | ADR-CC-001 — ADR-CC-007 | Audit transport, events topology, audit-Wazuh bridge, AI routing rule, idempotency, Parichay OAuth client, cross-namespace AI transport |
| Tijori | ADR-TIJORI-1, ADR-TIJORI-2 | Creation; API catalogue scope |
| Vani | ADR-VANI-5, ADR-VANI-8, ADR-VANI-9 | Retention classes, svc-vani realm roles, 72-hour correction window |
| Design | ADR-DESIGN-001 | Per-Setu design pack adoption |

---

## 15. Operational invariants — apply at every stage

These rules apply at every stage and to every Setu. They are non-negotiable:

1. **Sovereignty.** NIC NGC on-prem only. MinIO only. No AWS / Azure / GCS.
2. **Rajya Sabha only at the application layer.** No `lok-sabha-*` tenants, clients, or buckets.
3. **Sarvam discipline.** Saarika v2 is the only Sarvam product. Everything else is in-cluster open-weight.
4. **No model loading outside Tijori.** Section 2 non-negotiable #5.
5. **GPG-signed commits, every commit.** Conventional Commits with FR/NFR/SR id.
6. **No human can declassify in Samiti alone.** Two-staff approval required.
7. **No interception primitive, anywhere.** Hard rule from root AGENTS.md.
8. **Doc style: header / change-log / sign-off**, install-runbook format for the Software Manager audience.
9. **Audit immutable.** Every AI-bearing call emits a hash-chained audit row through Common Core.
10. **Egress allow-list verified at startup.** Adding a new external FQDN requires an ADR.

---

## 16. Glossary

| Term | Meaning |
|---|---|
| **Setu** | "Bridge" in Sanskrit; the SDS naming convention for each application surface |
| **SDS** | Sampurna Digital Sansad — the programme |
| **DRPSC** | Department-Related Parliamentary Standing Committee (Rajya Sabha) |
| **NIC NGC** | National Government Cloud (NIC-hosted sovereign cloud) |
| **NIC Parichay** | NIC-hosted identity provider; federated into Keycloak per ADR-4.2 |
| **Common Core** | The four sibling library repositories shared across every Setu |
| **Tijori** | The single AI control plane; the only Setu that loads models |
| **Vani** | The language-bridge Setu; separate GPU container |
| **LARDIS** | Legacy Lok Sabha and Rajya Sabha Document Indexing System; replaced by Samagrya |
| **C-DAC eSign** | Centre for Development of Advanced Computing eSign API (Aadhaar-backed digital signatures) |
| **Bhashini** | Government of India's national language-translation initiative; Dhruva pipeline |
| **Mayan EDMS** | Open-source document management system used by Samagrya and Samiti |
| **MinIO** | The only object-storage system permitted in the programme |
| **Linkerd** | The service mesh providing mTLS across every namespace |
| **OPA** | Open Policy Agent — admission and runtime authorisation |
| **AT-id** | Acceptance Test identifier in the per-Setu AT catalogue |
| **FR / NFR / SR** | Functional / Non-Functional / Security Requirement identifiers used in commit-message conventions |
| **UV** | Astral's Python toolchain — venv + pip + resolver in one tool; the SDS Python pin |

---

## 17. Sign-off

| Role | Name | Signature | Date |
|---|---|---|---|
| Software Manager | Kushal Pathak | _pending GPG signature under `7A096B6C23D1AAD4`_ | _pending_ |
| Solution Architect | _to be assigned_ | _pending_ | _pending_ |
| CISO Reviewer | _to be assigned_ | _pending_ | _pending_ |
| DPDP Compliance Reviewer | _to be assigned_ | _pending_ | _pending_ |
| Incoming Engineering Lead | _to be assigned_ | _on receipt_ | _pending_ |

---

## 18. Footer

Confidential | Rajya Sabha Secretariat | Programme: Sampurna Digital Sansad | Document: SDS-DEV-HANDOVER-2026-05-19 | Authoritative until superseded by the next versioned handover.
