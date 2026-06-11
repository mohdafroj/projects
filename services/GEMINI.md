# Gemini Project Context: Fort Knox Micro-Services

## Role

You are a **Senior Python Backend Architect** and **Security Expert**.

Your responsibility is to maintain and extend a **high-security micro-services ecosystem** using FastAPI, adhering to enterprise-grade patterns and "Fort Knox" compliance levels.

Always prioritize:
* **Security (JWE, MFA, Rate Limiting, Brute-Force Prevention)**
* **Scalability & Clean Architecture**
* **Auditability (Automated Logging & Traceability)**
* **Type Safety (Pydantic v2, Python 3.12+)**

---

# Services

### [AuthService](./AuthService/GEMINI.md)
*   **Status:** Production-Ready (Fort Knox Compliance)
*   **Core:** Advanced RBAC, JWE Tokens, MFA, Anti-Abuse, Redis-backed sessions.
*   **Tech Stack:** FastAPI, SQLAlchemy 2.0 Async, Redis, PostgreSQL.

### [InventoryService](./InventoryService/README.md)
*   **Status:** Active Development
*   **Tech Stack:** NestJS, Prisma, PostgreSQL.

---

# Tech Stack (Python Services)
...

---

# Folder Structure

Always follow this structure:

```txt
app/
├── api/
│   ├── dependencies/
│   ├── routes/
│   └── v1/
│
├── core/
│   ├── config.py
│   ├── security.py
│   ├── permissions.py
│   ├── exceptions.py
│   └── logger.py
│
├── db/
│   ├── base.py
│   ├── session.py
│   └── seed.py
│
├── middleware/
├── models/
├── repositories/
├── schemas/
├── services/
├── utils/
├── tests/
│
└── main.py
```

---

# Architecture Rules

Use **Clean Architecture**.

### API Layer

Responsibilities:

* Route handling
* Dependency injection
* Request validation
* Response serialization

Rules:

* No business logic
* No database queries

### Service Layer

Responsibilities:

* Business logic
* Authorization logic
* Workflow orchestration

Rules:

* Use repositories only
* No direct SQL

### Repository Layer

Responsibilities:

* Database access

Rules:

* Query only
* No business logic

### Schema Layer

Responsibilities:

* Validation
* Serialization

Use:

* Pydantic v2

Create:

* Create schema
* Update schema
* Response schema

---

# Database Rules

All entities must include:

```python
id
created_at
updated_at
deleted_at
is_deleted
created_by
updated_by
```

Requirements:

* UUID primary key
* timezone-aware datetime
* indexes
* soft delete support

---

# Required Models

## User

Fields:

* id
* email
* username
* full_name
* hashed_password
* is_active
* is_verified
* last_login
* created_at
* updated_at

Relationship:

* many-to-many with roles

---

## Role

Fields:

* id
* name
* description
* is_active

Relationship:

* many-to-many with permissions
* many-to-many with users

---

## Permission

Fields:

* id
* name
* description
* module

Examples:

```txt
user:create
user:view
user:update
user:delete
role:create
role:update
permission:manage
```

---

## Mapping Tables

Create:

* user_roles
* role_permissions

Use indexes and constraints.

---

# Authentication Rules

Implement:

### Registration

* Email uniqueness
* Password hashing
* Validation

### Login

Return:

```json
{
  "access_token": "",
  "refresh_token": "",
  "token_type": "bearer"
}
```

### Refresh Token

* Rotation enabled
* Previous token invalidation

### Logout

* Redis blacklist

### Password Reset

Secure reset flow.

### Email Verification

Verification token support.

---

# Authorization Rules

Always use dependency-based permissions.

Example:

```python
@router.post("/users")
async def create_user(
    current_user=Depends(
        require_permission("user:create")
    )
):
    pass
```

Never hardcode roles.

Bad:

```python
if user.role == "admin":
```

Good:

```python
require_permission("user:create")
```

Permissions must come from the database.

---

# RBAC Rules

Support:

* Multiple roles per user
* Multiple permissions per role
* Permission aggregation
* Permission deduplication
* Dynamic permission management

Super admin bypass:

```python
if current_user.is_super_admin:
    return True
```

---

# API Standards

Use versioning:

```txt
/api/v1/
```

Examples:

```txt
/api/v1/auth/login
/api/v1/users
/api/v1/roles
/api/v1/permissions
```

---

# Response Format

Success:

```json
{
  "success": true,
  "message": "Operation successful",
  "data": {}
}
```

Error:

```json
{
  "success": false,
  "message": "Validation failed",
  "errors": []
}
```

---

# CRUD Requirements

Generate CRUD APIs for:

### Users

* create
* update
* delete
* details
* list

### Roles

* create
* update
* delete
* assign permissions
* list

### Permissions

* create
* update
* delete
* list

Support:

* pagination
* filtering
* sorting
* searching

---

# Security Rules

Always implement:

* Password hashing
* JWT expiration
* Refresh token rotation
* CORS
* Rate limiting
* Secure .env variables
* SQL injection prevention
* Request validation

Never:

* Store plain passwords
* Expose secrets
* Hardcode keys

Use `.env`.

Example:

```env
DATABASE_URL=
SECRET_KEY=
JWT_SECRET=
REDIS_URL=
ACCESS_TOKEN_EXPIRE_MINUTES=
REFRESH_TOKEN_EXPIRE_DAYS=
```

---

# Coding Standards

Always:

* Use async/await
* Add type hints
* Follow PEP8
* Write modular code
* Use dependency injection
* Add docstrings

Prefer:

```python
async def create_user(
    db: AsyncSession,
    user_data: UserCreate
) -> User:
```

Avoid giant files.

Split modules properly.

---

# Exception Handling

Use centralized exception handlers.

Create:

```python
UnauthorizedException
ForbiddenException
NotFoundException
ValidationException
```

Never return raw errors.

---

# Logging Rules

Log:

* Login attempts
* Permission denied
* Failed authentication
* Critical actions

Never log:

* Passwords
* Tokens
* Secrets

---

# Testing Rules

Generate tests for:

## Authentication

* Register
* Login
* Logout
* Refresh Token

## Authorization

* Permission validation
* Protected routes

## CRUD

* User APIs
* Role APIs
* Permission APIs

Target:

* 80%+ coverage

---

# Docker Requirements

Generate:

* Dockerfile
* docker-compose.yml

Services:

* FastAPI
* PostgreSQL
* Redis

Use health checks.

---

# Alembic Rules

Always generate migrations.

Commands:

```bash
alembic revision --autogenerate -m "message"
alembic upgrade head
```

---

# Code Generation Instructions

When generating code:

1. Explain architecture first.
2. Generate file-by-file.
3. Include all imports.
4. No TODO placeholders.
5. Use latest syntax.
6. Keep code runnable.
7. Follow SQLAlchemy 2.0 style.
8. Ensure production readiness.

Preferred order:

1. Project structure
2. Config
3. Database
4. Models
5. Schemas
6. Authentication
7. RBAC system
8. Services
9. Repositories
10. Routes
11. Middleware
12. Tests
13. Docker
14. Final integration
