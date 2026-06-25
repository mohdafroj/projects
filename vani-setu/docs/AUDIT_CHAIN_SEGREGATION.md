# Audit Chain Segregation

## Segments

Audit rows carry `chain_segment` so verification can prove continuity within sensitive workstreams without requiring unrelated audit activity to sit between two rows in the same chain.

The supported segments are:

- `on_record`: default chain for legacy rows and actions outside the specialised workstreams.
- `reporter`: actions prefixed with `reporter.`.
- `translator`: actions prefixed with `translator.`.
- `committee`: actions prefixed with `committee.`.
- `committee.in_camera`: in-camera sub-chain for actions prefixed with `in_camera.` or `committee.in_camera.`.

Existing rows are migrated to `on_record` by the `chain_segment` column default. New rows are written only through `AuditLogger`, which resolves the segment and stores the previous hash from the latest row in that same segment.

## Verification

`php artisan audit:verify` verifies every segment independently and prints a status line per segment plus a total row count. To verify a subset:

```bash
php artisan audit:verify --segment=reporter --segment=translator
```

For rows written before `chain_segment` existed, verification accepts the legacy hash pre-image after confirming the row remains in the expected segment order. Rows written after this change include `chain_segment` in the hash pre-image, so moving a row between segments breaks verification.

## Operational Rules

- Do not insert audit rows directly. Use `AuditLogger::log()` so segment routing, per-segment previous hash lookup, request context, and hash generation remain consistent.
- Do not update audit rows to correct segment placement. Append a compensating audit event through `AuditLogger` and investigate the caller that emitted the wrong action prefix.
- In-camera audit activity stays inside `committee.in_camera`; it does not advance the public `committee` chain.
