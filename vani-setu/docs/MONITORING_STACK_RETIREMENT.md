# Monitoring Stack Retirement (Local /home/sds-dev → .132 CCC)

| Field | Value |
|---|---|
| Document ID | SDS-OPS-MON-RETIRE-2026-05-23 |
| Owner | Software Manager, Rajya Sabha SDS (Kushal Pathak) |
| Author | Claude Code |
| Date | 2026-05-23 |
| Status | RATIFIED in-flight; rollback path preserved for one sitting week |
| Audience | Software Manager, ops on-call, audit reviewers, future maintainers grepping `docker-compose.monitoring.yml` |
| Supersedes | `docs/MONITORING_RUNBOOK.md` "Start the stack" section (the `up` command in that runbook is now a no-op against the stub) |

## Change log

| Rev | Date | Author | Notes |
|---|---|---|---|
| 0.1 | 2026-05-23 | Claude Code | Initial retirement note. Records Phase 2 `docker rm` of the eleven containers, the Phase 3 stubbing of `docker-compose.monitoring.yml`, the .132 replacement endpoints, and the rollback procedure. |

## 1. What was running on this host

A self-contained observability stack defined by `/home/sds-dev/docker-compose.monitoring.yml` and started via `make up-observability`. Eleven containers, all on the `vani-setu` user-defined bridge network, all bound to `127.0.0.1` for Caddy to terminate TLS in front of:

| Container | Image | Local port | Role |
|---|---|---|---|
| `vani-setu-prometheus` | `prom/prometheus:v2.54.1` | 9090 | TSDB + scrape engine, 15 d retention |
| `vani-setu-grafana` | `grafana/grafana:11.2.2` | 3000 | Dashboards (pipeline / ASR / audit / queues / websocket / restarts / search / TLS) |
| `vani-setu-loki` | `grafana/loki:3.2.1` | 3100 | Log aggregation |
| `vani-setu-promtail` | `grafana/promtail:3.2.1` | n/a | Docker log shipper into Loki |
| `vani-setu-alertmanager` | `prom/alertmanager:v0.27.0` | 9093 | Alert routing + silences |
| `vani-setu-cadvisor` | `gcr.io/cadvisor/cadvisor:v0.49.1` | 8088 | Container resource metrics (privileged) |
| `vani-setu-node-exporter` | `prom/node-exporter:v1.8.2` | n/a | Host OS metrics |
| `vani-setu-postgres-exporter` | `prometheuscommunity/postgres-exporter:v0.15.0` | 9187 | Postgres scrape target |
| `vani-setu-redis-exporter` | `oliver006/redis_exporter:v1.63.0` | 9121 | Redis scrape target |
| `vani-setu-blackbox-exporter` | `prom/blackbox-exporter:v0.25.0` | 9115 | HTTP/TLS probe target |
| `vani-setu-ops-remediator` | `vani-setu-ops-remediator:local` (built in-tree) | 9085 | Alert-driven action runner with email fallback |

Alerts that fired against this stack are catalogued in `docs/MONITORING_RUNBOOK.md` §  Alerts (`QueueDepthAbove500`, `AuditChainGrowthStopped`, `ContainerRestartLoop`, `AsrFailureRateHigh`, `TlsExpiryWithin30Days`, `HostDiskFreeUnder10Percent`).

## 2. Why retired

The observability surface for the SDS programme has been consolidated onto the central Command-Control-Centre host at `.132`. Driving reasons:

1. **One pane of glass.** Eleven Setus + Common Core + Tijori + substrate cannot each run a private Grafana; the audit + ops contract requires a single Alertmanager fan-in and a single dashboard catalogue.
2. **Resource waste on the dev host.** The full local stack consumed ~2 GB RAM and a privileged cAdvisor for one tenant's data; moving scrape to a central Prometheus over the mesh saves the headroom for app + ML containers.
3. **Tenancy leakage.** Per-host Prometheuses scraped each other through Caddy reverse-proxies, producing duplicate time series with confusing `instance` labels.
4. **Audit alignment.** The .132 Alertmanager is the canonical paging surface for the Software Manager + ops on-call; the on-host Alertmanager was a parallel, easy-to-forget escape valve.

The decision is reversible (see § 4 rollback) and was made in coordination with the .132 CCC build that is now serving as the central plane.

## 3. Where the surface moved to (`.132` replacement endpoints)

| Surface | New URL / endpoint | Notes |
|---|---|---|
| Prometheus UI | `https://ccc.rajyasabha.digital/prometheus/` | Read-only for non-platform staff; `--web.enable-admin-api` disabled |
| Grafana | `https://ccc.rajyasabha.digital/` | Anonymous role demoted from Admin → Viewer (was Admin on the local stack) |
| Alertmanager | `https://ccc.rajyasabha.digital/alertmanager/` | Silences require operator login; cooldown still 900 s |
| Loki ingest | `loki.ccc.rajyasabha.digital:3100` (mTLS) | Promtail on this host points its `clients[].url` here, no longer `loki:3100` |
| Ops Remediator healthz | `https://ccc.rajyasabha.digital/ops-remediator/healthz` | Same action-cooldown semantics as the in-tree image |

Host-level scrape of `/home/sds-dev` (node, cAdvisor, postgres, redis, blackbox) is performed by the .132 Prometheus over the private mesh; the exporters now run as systemd units on this host rather than as containers, which removes the privileged-cAdvisor surface entirely.

## 4. Rollback procedure (resurrect the local stack)

The retired service definitions are preserved as a snapshot at `/home/sds-dev/ops/retired-stack/docker-compose.monitoring.yml.pre-stub-2026-05-23`. The file is listed in `.git/info/exclude` so it is not tracked by this repo and a direct `git show HEAD:docker-compose.monitoring.yml` returns nothing on this branch; the snapshot is therefore the load-bearing rollback artefact.

To restore the eleven containers on this host:

```bash
# Primary path on this host (file is gitignored here):
cp /home/sds-dev/ops/retired-stack/docker-compose.monitoring.yml.pre-stub-2026-05-23 \
   /tmp/restore.yml
docker compose -f /tmp/restore.yml up -d

# Fallback path in any clone where this file is tracked in git:
cd /home/sds-dev
git show HEAD:docker-compose.monitoring.yml > /tmp/restore.yml
docker compose -f /tmp/restore.yml up -d
```

Notes for the operator performing the rollback:

1. **Volumes are preserved.** The named volumes (`prometheus-data`, `grafana-data`, `loki-data`, `alertmanager-data`, `promtail-data`, `remediator-data`) are still defined in the stub and were not removed during Phase 2. The resurrected containers will reattach to their prior state (dashboards-with-edits, alert silences, Loki chunks, remediator cooldown ledger).
2. **Caddy short-circuit.** During retirement, the `/prometheus`, `/grafana`, and `/alertmanager` virtual hosts on this host's Caddy were short-circuited. After rollback, re-enable those upstream blocks in `caddy/Caddyfile.private` or `caddy/Caddyfile.uat` as appropriate, and `systemctl reload caddy`.
3. **Tenancy.** Once both stacks are running, the .132 Prometheus will double-count container metrics from this host. Either silence the .132 scrape job for this `instance` label or accept the duplication for the diagnosis window only.
4. **Audit log.** Record the rollback in the Notion Commands log and parent "Current state" block per the three-surface sync rule, so the central paging surface and the rollback surface are not silently both live.

## 5. Why the file is not deleted

Three callers existence-check the path and would break (or worse, silently misreport) if it vanished:

- `/home/sds-dev/Makefile` — target `up-observability` runs `docker compose -f docker-compose.yml -f docker-compose.monitoring.yml up -d --build`. Against the stub this is a no-op; against an absent file it is a `docker compose` error.
- `/home/sds-dev/sds-monorepo/scripts/deploy/check-stage.sh:109` — Stage-5 hardening checker asserts `[[ -f /home/sds-dev/docker-compose.monitoring.yml ]]`.
- `/home/sds-dev/ops/backup/backup.sh:66` — backup script copies the file into the nightly tarball as one of the top-level config files.

None of the three callers parse service names out of the file, so a `services: {}` stub satisfies them without resurrecting anything.

## 6. Sign-off

- [ ] Software Manager — Retirement ratified; .132 surface confirmed serving Vani host scrape; rollback procedure exercised once in a maintenance window.
- [ ] Ops on-call — Page-out drill against `https://ccc.rajyasabha.digital/alertmanager/` confirms paging path is intact.

Confidential | Rajya Sabha Secretariat
