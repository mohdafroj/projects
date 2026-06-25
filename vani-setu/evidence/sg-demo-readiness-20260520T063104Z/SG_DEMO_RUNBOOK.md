# Vani Setu SG Demo Runbook

Audience: Rajya Sabha Secretariat stakeholders validating the SG decision workflow.

## URL

- Staging target: `https://vanisetu.rajyasabha.digital`
- Private-network fallback: `https://vanisetu.rajyasabha.digital`
- Direct UAT: `https://uat.vanisetu.rajyasabha.digital:9443`
- Local fallback: `http://localhost:5173`

The private-network fallback uses an mkcert certificate mounted into Caddy. Public ACME issuance still depends on DNS and port ownership work listed in `/home/sds-dev/docs/NETWORK_HANDOFF.md`.

## Trust the Private TLS Certificate

On the demo host, the mkcert root is installed at:

```bash
/home/sds-dev/.local/share/mkcert/rootCA.pem
```

For the SG demo laptop:

1. Copy `rootCA.pem` to the laptop over an approved secure channel.
2. Install it into the OS trust store.
   - Windows: run `certmgr.msc`, import into `Trusted Root Certification Authorities`.
   - macOS: open Keychain Access, import into `System`, set trust to `Always Trust`.
   - Ubuntu: copy to `/usr/local/share/ca-certificates/vanisetu-mkcert.crt`, then run `sudo update-ca-certificates`.
3. Open `https://vanisetu.rajyasabha.digital` and confirm there is no browser warning.

## Demo Credentials

| Role | Employee ID | Password |
| --- | --- | --- |
| Reporter | `RPT-001` | `reporter123` |
| Supervisor | `SUP-EN-001` | `sup123` |
| Chief EN | `CHF-EN-001` | `chief123` |
| Chief HI | `CHF-HI-001` | `chief123` |
| JS | `JS-001` | `js123` |
| SG | `SG-001` | `sg123` |
| Director | `DIR-001` | `director123` |
| Admin | `ADM-001` | `admin123` |

## Reset Demo Data

```bash
cd /home/sds-dev
docker compose exec app php artisan db:seed --class=RealSittingSeeder --force
docker compose exec app php artisan audit:verify
```

The seeder uses the real 01-12-2025 sitting PDFs when `/mnt/project` is mounted. If those PDFs are absent, it falls back to deterministic Rajya Sabha-shaped transcript content while preserving the same 60-slot workflow distribution.

## Stakeholder Walkthrough

1. Reporter: open `/capture`, claim/open a slot, edit a transcript block, commit the lane.
2. Supervisor: open `/supervisor/queue`, review a committed lane, forward it to Chief.
3. Chief EN: open `/chief/queue`, review a consolidation, edit one block, commit EN.
4. Chief HI: open the same consolidation from `/chief/queue`, edit one block, commit HI.
5. JS: open `/js/queue`, accept one suggested edit, forward the window to SG.
6. SG: open `/sg/tray`, open a window, confirm expunge candidates, sign with the DSC stub.
7. Director: open `/director/inbox`, publish the queued job, confirm CRC preview and Digital Sansad push.
8. Admin: open `/admin/system-health` to confirm Laravel, Postgres, Redis, frontend, ML gateway, realtime sidecar, and Meilisearch are healthy.

## Evidence

- UI smoke screenshots: `/home/sds-dev/evidence/ui-smoke-20260520T051714Z/`
- TLS UI smoke screenshots: `/home/sds-dev/evidence/ui-smoke-tls-20260520T061100Z/`
- Restore drill logs: `/home/sds-dev/evidence/restore-drill-*.log`
- Backups: `/var/backups/vanisetu/daily/`
- Audit JSONL exports: `/var/backups/vanisetu/audit/`

## Operations

```bash
cd /home/sds-dev
docker compose ps
docker compose logs --tail=120 caddy
BACKUP_DIR=/var/backups/vanisetu ./scripts/backup.sh
BACKUP_DIR=/var/backups/vanisetu ./scripts/restore-drill.sh
```

Nightly backups are installed through `/etc/cron.d/vanisetu-backup`.

## Dry Run Notes

The automated SG dry run completed over TLS in about 30 seconds for the scripted path. Human target remains 15 minutes.

Observed refinements:

- Open directly on the role queue for each stakeholder to avoid explaining navigation.
- For SG, state before clicking that `DSC Sign` is enabled only after all pending expunge candidates are confirmed.
- For Director, call the preview "CRC preview" consistently; avoid switching between "publish" and "push" in narration.
- Keep `/admin/system-health` open in a separate tab before the meeting starts.

Screen recording path for asynchronous review:

- `/home/sds-dev/evidence/sg-demo-dry-run-20260520T061100Z.webm`
