# Security Exceptions

| Date | Scanner | Finding | Scope | Disposition |
|---|---|---|---|---|
| 2026-05-20 | npm audit / Trivy dependency DB | `esbuild` / `vite` dev-server advisory in Vitest/Vite dependency chain | Dev/test tooling only; not bundled into Laravel backend production runtime | Accepted for dev/stage RC. Production image exposure must be re-checked before production approval. |

No exception may cover secret exposure, authentication bypass, production object-store drift, or critical/high findings in production code.
