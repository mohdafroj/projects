# Production Readiness

Open items: 8

## 1. Vault Integration

Every secret in `.env` must move to Vault and be read at boot by a sidecar or init container.

| Secret | Production source |
| --- | --- |
| `APP_KEY` | Vault `secret/vani-setu/app-key` |
| `DB_PASSWORD` | Vault dynamic Postgres credential for `sb-pg` |
| `BOOTSTRAP_ADMIN_PASSWORD` | Vault one-time bootstrap secret, disabled after cutover |
| `REDIS_PASSWORD` | Vault shared Redis credential |
| `REVERB_APP_SECRET` | Vault `secret/vani-setu/reverb` |
| `SB_IAM_CLIENT_SECRET` | Vault `secret/sb-iam/vani-setu` |
| `REALTIME_AUDIT_SECRET` | Vault `secret/vani-setu/realtime-audit` |
| ASR provider keys | Vault provider-specific paths for Sarvam, Bhashini, and local fallback |

## 2. Database

Production must use the shared-infra `sb-pg` Postgres cluster, not the local container. Connection string and credentials must come from Vault.

## 3. Backups

Nightly `pg_dump` must replicate off-host to approved object storage or backup NAS. Target proposal:

- Primary: `s3://rs-secure-backups/vani-setu/postgres/`
- Secondary audit JSONL: `s3://rs-secure-backups/vani-setu/audit-jsonl/`

## 4. Monitoring Baseline

Prometheus scrape endpoints required:

- Laravel API: health, request latency, audit chain depth.
- Horizon: queue depth and failed jobs.
- Reverb: websocket connection count and disconnect rate.
- ML gateway: ASR latency, provider circuit state, fallback count.
- Meilisearch: search QPS and indexing failures.
- Caddy: TLS expiry, HTTP status rates.

Grafana dashboards:

- Queue depth and failed jobs.
- Websocket connections.
- ASR latency by provider.
- Search QPS.
- Audit chain depth growth rate.

## 5. Alerting

Initial thresholds:

- Queue depth above `100` for more than 10 minutes.
- Any audit chain break.
- Container restart loop: more than 3 restarts in 10 minutes.
- TLS expiry within 30 days.
- ASR failure rate above 5 percent over 15 minutes.
- Postgres replication lag above 60 seconds after shared infra cutover.

## 6. Incident Response

Required before production:

- Named primary and secondary on-call owners.
- Escalation path to Secretariat IT, network team, database team, and application owner.
- Evidence preservation: never truncate, update, or delete `audit_logs`.
- For suspected audit compromise, snapshot DB and container logs before remediation.

## 7. Real DSC Adapter

Replace `LocalStubDscAdapter` with C-DAC eSign integration:

- PFX certificate custody through HSM or approved encrypted store.
- Timestamping authority integration.
- Certificate serial and signature hash stored with SG review.
- Dry run with invalid/expired certificate paths before production.

## 8. Real ASR Fallback Chain

Validate measured latency and quality for:

- Sarvam primary.
- Bhashini secondary.
- Whisper local fallback.

Record per-provider latency, error rate, language coverage, and fallback behavior under load before cutover.
