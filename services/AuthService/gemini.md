# AuthService: Advanced RBAC & Security System

This service is a production-ready Identity and Access Management (IAM) and Role-Based Access Control (RBAC) system built with FastAPI. It implements "Fort Knox" level security with 22 distinct security layers.

## Core Features

- **Identity:** JWE-encrypted tokens (AES256GCM), Refresh Token Rotation, Device Binding.
- **MFA:** TOTP (Google Authenticator) support.
- **RBAC:** Dynamic Roles and Permissions stored in PostgreSQL, evaluated via FastAPI dependencies.
- **Anti-Abuse:** Redis-backed Rate Limiting, Account Lockout, Captcha, Pwned Password checks (k-Anonymity).
- **Audit:** Automated audit logging for all state-changing operations.
- **Traceability:** Request Correlation IDs across all logs.

## Tech Stack

- **Framework:** FastAPI
- **Language:** Python 3.12+
- **Database:** PostgreSQL (SQLAlchemy 2.0 Async)
- **Cache:** Redis (Rate limiting, Blacklisting, Lockouts)
- **Migrations:** Alembic
- **Testing:** Pytest + HTTPX

## Getting Started

### Prerequisites

- Docker and Docker Compose
- Python 3.12
- Redis and PostgreSQL (or use Docker)

### Local Setup

1. **Environment:** Copy `.env.example` to `.env` and fill in the values.
2. **Install Dependencies:**
   ```bash
   pip install -r requirements.txt
   ```
3. **Run Migrations:**
   ```bash
   alembic upgrade head
   ```
4. **Seed Database:**
   ```bash
   python -m app.db.seed
   ```
5. **Run Application:**
   ```bash
   uvicorn app.main:app --reload
   ```

## Development Conventions

### 1. Clean Architecture
Follow the established layer separation:
- `api/`: Routes and Dependencies. No logic.
- `services/`: Business logic and orchestration.
- `repositories/`: Database access (SQLAlchemy).
- `models/`: Database schemas (SQLAlchemy).
- `schemas/`: Data validation (Pydantic v2).

### 2. Security First
- Always use `require_permission("module:action")` for protected routes.
- Never log sensitive data (automatically masked by `CustomFormatter`).
- All state-changing routes MUST be audit-logged (handled by `AuditLogMiddleware`).

### 3. Database
- All models must inherit from `Base` (which includes `id`, `created_at`, etc.).
- Use UUIDs for primary keys.
- Prefer soft deletes where appropriate.

## Testing

Run the full security and regression suite:
```bash
pytest
```
Coverage target: **80%+**

## Deployment

Use the provided `Dockerfile` and `docker-compose.yml`. The container runs as a non-root `appuser` for security.

---
*For detailed security implementation, see `SECURITY_POLICIES.md`.*
*For technical interview preparation, see `QA.md`.*
