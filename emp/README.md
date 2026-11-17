# PeopleDesk — Employee Management System

An Angular + TypeScript employee dashboard backed by an Express REST API and MongoDB. The dashboard follows the supplied reference: responsive sidebar, employee overview cards, hires/exits chart, searchable and sortable directory, and employee create/edit workflows.

## Requirements

- Node.js 20.19+ or 22.12+
- MongoDB 7+ (local or a MongoDB-compatible Azure Cosmos DB account)
- Docker Desktop with Docker Compose

## Run in development with Docker Compose

Start the MongoDB database, Express API in watch mode, and Angular development server with live source mounts:

```powershell
docker compose up --build
```

In another terminal, create the development admin and sample employees:

```powershell
docker compose --profile tools run --rm seed
```

Open <http://localhost:4200>. Sign in with `ADMIN_EMAIL` / `ADMIN_PASSWORD` from the Compose environment; the defaults are `admin@example.com` and `local-dev-password-123` for local development only. Set `ADMIN_EMAIL`, `ADMIN_PASSWORD`, and `JWT_SECRET` in a root `.env` file to override the defaults. The Angular container proxies `/api` requests to the API service; source edits are watched in both containers. MongoDB data persists in the `mongodb_data` volume.

Stop the services with `docker compose down`. To also remove the local database, run `docker compose down --volumes`.

## Run locally without Docker

1. Configure the API:

   ```powershell
   Copy-Item api\.env.example api\.env
   ```

   Set `MONGODB_URI`, a long random `JWT_SECRET`, and a private `ADMIN_PASSWORD` in `api\.env`.
2. Install dependencies and initialize the administrator and sample dashboard data:

   ```powershell
   npm install --prefix api
   npm install --prefix client
   npm run seed --prefix api
   ```
3. Start the API and Angular app in separate terminals:

   ```powershell
   npm run start:api
   npm run start:web
   ```
4. Open <http://localhost:4200> and sign in with `ADMIN_EMAIL` / `ADMIN_PASSWORD` from `api\.env`.

The Angular development server proxies API requests through `/api`; `client/proxy.conf.json` sets the local API target.

## Features

- JWT sign-in, session persistence, logout, and authenticated API requests.
- RBAC roles: `admin` and `hr` can manage employees; `manager` and `viewer` can view.
- Employee create, read, update, and delete with server-side validation.
- Directory search, status/department filters, server-side sorting and pagination, and CSV export of the current page.
- Reactive forms with required/email/length validation and inline API errors.
- Dashboard summary metrics and monthly hires/exits chart.
- Helmet security headers, login rate limiting, structured API errors, and a health endpoint at `/api/health`.
- Jest unit tests for authentication and role enforcement; Angular Karma/Jasmine test runner is configured.

## Tests and production build

```powershell
npm test
npm run build
```

Angular tests can be run with `npm test --prefix client` when Chrome/Chromium is available.

## Azure deployment

Deploy as a Linux container to Azure App Service. Provision an Azure Cosmos DB account with MongoDB API (or an externally hosted MongoDB database), then configure these App Service application settings:

- `MONGODB_URI` — Cosmos DB/MongoDB connection string, including TLS settings as required by the provider.
- `JWT_SECRET` — unique, long random secret stored as an App Service setting or Key Vault reference.
- `JWT_EXPIRES_IN` — optional, defaults to `8h`.
- `ADMIN_EMAIL`, `ADMIN_PASSWORD`, `ADMIN_NAME` — bootstrap account values.
- `PORT` — App Service supplies this automatically.

Build and push the repository's `Dockerfile` to Azure Container Registry, configure the App Service container to use that image, and enable HTTPS-only access. Run `npm run seed --prefix api` once against the production database from a secure deployment environment to create the administrator and optional initial sample records. Do not commit `.env` files or production secrets.

For local Docker testing, build from this directory and provide the environment variables above when running the container. The API serves the compiled Angular app and `/api/health` from the same origin.

## API

- `POST /api/auth/login` — authenticate with email and password.
- `GET /api/auth/me`, `POST /api/auth/logout` — authenticated session endpoints.
- `POST /api/auth/users` — `admin` only; provisions an `admin`, `hr`, `manager`, or `viewer` account.
- `GET /api/employees/summary` — dashboard aggregates.
- `GET /api/employees?search=&status=&department=&sortBy=&sortOrder=&page=&limit=` — paginated employee list.
- `POST /api/employees`, `PUT /api/employees/:id`, `DELETE /api/employees/:id` — `admin` / `hr` only.

Logout clears the browser's persisted bearer token; as with other stateless access JWTs, an already-issued token remains valid until expiry.
