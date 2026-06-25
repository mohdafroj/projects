# Vani Setu Parallel Agent Contract

## Module Ownership

Each agent owns exactly one `app/Modules/{Module}` subdirectory. SGDIR may own two adjacent module directories when its workflow crosses Secretary General and Director handoff. Cross-writes into another agent's module are forbidden.

The only shared writes allowed per agent are:
- Append the module name to `/opt/vanisetu/frontend/src/modules/MODULE_INDEX.ts`.
- Create the module's own `docker-compose.{module}.yml` override at the repository root.

## Backend Module Layout

Each Laravel module follows this shape:

```text
app/Modules/{Module}/
  Migrations/
  Models/
  Controllers/
  Policies/
  Requests/
  Services/
  Seeders/
  Tests/
  routes-api.php
  module.json
```

Shared Eloquent models live in `App\Modules\Core\Models`. Feature modules should import those models instead of duplicating model classes.

## Migration Timestamp Partition

Use unique seconds offsets to avoid parallel migration collisions:

- CHIEF: timestamps end in `001` (`2026_05_19_HHMMS001_*`)
- JS: timestamps end in `002`
- SGDIR: timestamps end in `003`
- TRANSLATOR: timestamps end in `004`
- MLGW: no Laravel migrations; Python service only
- RTSEARCH: timestamps end in `005`
- SPEECH_TO_SPEECH: timestamps end in `006`
- SYNOPSIS: timestamps end in `007`
- FORMATTING: timestamps end in `008`
- MASTER_DASH: timestamps end in `009`
- SG_DASH: timestamps end in `010`
- REPORTS: timestamps end in `011`
- WORKFLOW_BOARD: timestamps end in `012`
- APPROVAL_QUEUE: timestamps end in `013`
- LIVE_CHAMBER: timestamps end in `014`
- REGIONAL: timestamps end in `015`
- ADMIN_FULL: timestamps end in `016`
- COMMITTEE_SITTINGS: timestamps end in `017`
- COMMITTEE_CAPTURE: timestamps end in `018`
- COMMITTEE_SUPERVISOR: timestamps end in `019`
- COMMITTEE_CHIEF: timestamps end in `020`
- COMMITTEE_SECRETARIAT: timestamps end in `021`
- COMMITTEE_CHAIR: timestamps end in `022`
- COMMITTEE_REPORTS: timestamps end in `023`
- IN_CAMERA: timestamps end in `024`

## Audit Actions

Every state-changing write must call:

```php
AuditLogger::log('{module}.{noun}.{verb}', ...)
```

Examples: `capture.block.edit`, `workflow.forward.chief`, `search.index.refresh`.

## Test Isolation

Every module feature test class extends `Tests\ModuleTestCase`. That base class uses `RefreshDatabase` and seeds:

- `AuditGenesisSeeder`
- `RolePermissionSeeder`
- `DemoSittingSeeder`
- `DemoBlockSeeder`
- `DemoReporterSeeder`
- `DemoAssignmentSeeder`
- `DemoSupervisorSeeder`

Module-specific seeders should run after those base seeders.

## Docker Compose Overrides

`docker-compose.yml` is the base stack. Modules that need new services add their own `docker-compose.{module}.yml` override. Start the full local stack through:

```bash
./run-stack.sh
```
