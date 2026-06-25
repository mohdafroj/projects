# Vani Setu UAT Protocol

## Environment

- UAT URL: `https://uat.vanisetu.rajyasabha.digital:9443`
- UAT frontend direct port: `http://10.21.217.17:5273`
- UAT compose: `/home/sds-dev/docker-compose.uat.yml`
- Startup helper: `/home/sds-dev/scripts/uat-up.sh`
- UAT database: `vani_setu_uat` in the UAT Postgres container
- Production/demo database: `vani_setu` in the production Postgres container

Port note: `8443` is occupied by `sds-fake-parichay`, so UAT Caddy uses `9443`.

## Pilot Pool

Start with one low-volume SSA/SA pool. The recommended first pilot is a section with predictable English/Hindi floor version throughput and a supervisor who can spend 15 minutes at the end of each sitting on comparison.

## Parallel Run

For each selected sitting:

1. SSA/SA team produces the manual Floor Version using the current process.
2. The same audio/verbatim input is processed through Vani Setu in UAT.
3. Supervisor and Chief complete the Vani Setu review path.
4. JS and SG complete the decision path only for marked UAT windows.
5. Director generates a UAT CRC preview without publishing to any production destination.

## Daily Diff

At 22:00 IST:

```bash
/home/sds-dev/scripts/uat-diff.sh manual-fv.txt vani-setu-fv.txt
```

The diff buckets discrepancies into:

- `cosmetic`
- `transcription`
- `attribution`
- `workflow`
- `audit`

## Defect Tracker

Use a CSV or issue tracker with these fields:

```csv
date,sitting,slot,bucket,severity,manual_text,vani_text,owner,status,resolution
```

## Exit Criteria

Promote only after:

- 10 consecutive sittings with zero workflow defects.
- 10 consecutive sittings with zero audit chain breaks.
- No unresolved severity-1 transcription or attribution defects.
- SG and JS sign off on the runbook flow.

## Isolation Proof

Production Postgres cannot read UAT audit logs:

```bash
docker compose exec -T postgres psql -U vani -d vani_setu_uat -c 'select count(*) from audit_logs;'
```

Expected: database does not exist.

UAT Postgres cannot read production audit logs:

```bash
docker compose -f docker-compose.uat.yml exec -T uat-postgres psql -U vani -d vani_setu -c 'select count(*) from audit_logs;'
```

Expected: database does not exist.
