# Environment input and approval tracker

Complete this per environment before enabling `enable_remote_changes`.

| Input field | Staging | UAT | PROD |
| --- | --- | --- | --- |
| Environment owner | pending | pending | pending |
| Host IP / hostname | `10.21.217.17`, `10.21.217.132` | `10.21.217.17`, `10.21.217.132` | temporary dev IPs, replace before apply |
| SSH user and key reference | `sds-dev`, `~/.ssh/id_rsa` | `sds-dev`, `~/.ssh/id_rsa` | pending production SSH details |
| CPU / RAM / disk | pending | pending | pending |
| Terraform backend path | local pending remote backend | local pending remote backend | local pending remote backend |
| k3s version | `v1.35.4+k3s1` | `v1.35.4+k3s1` | `v1.35.4+k3s1` |
| Storage class | `local-path` | `local-path` | pending approval |
| DNS zone / owner | pending | pending | pending |
| TLS issuer / CA | `sds-ca-issuer` placeholder | `sds-ca-issuer` placeholder | pending approval |
| Vault path prefix | `kv/staging` | `kv/uat` | `kv/prod` |
| Harbor registry/project | `sds-staging` | `sds-uat` | `sds-prod` |
| GitOps repo branch | `staging` | `uat` | `prod` |
| Backup source / restore method | pending | pending | pending |
| Go-live approver | pending | pending | pending |

## Apply gates

- Staging: host ready, secrets referenced, internal DNS/TLS tested, Argo CD deployment path approved.
- UAT: UAT host ready, access reviewed, restore tested, business test users and sign-off path confirmed.
- PROD: production hosts hardened, DNS/TLS approved, backup and restore verified, rollback authority named, monitoring and escalation active.

Do not store secret values in Terraform variables, committed files, plan files, or state.
