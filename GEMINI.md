# Workspace Overview: Polyglot Micro-Services & Frontends

This workspace is a comprehensive collection of modern web development projects across multiple stacks, including Micro Frontends (MFE), various backend frameworks (Laravel, NestJS, FastAPI, Express), and frontend frameworks (React, Angular, Vue).

## Architecture & Project Structure

### Micro Frontends (MFE)
The project uses Webpack 5 Module Federation to orchestrate a distributed frontend.
- **Host App (`mfehost`):** The main entry point (Port 3000).
- **Remote Header (`remoteHeader`):** Shared header component (Port 3001).
- **Remote IAM (`remoteIAM`):** Identity and Access Management remote (Port 3002).

### Backend Services
- **Laravel (`laravel11`):** PHP 11 framework with MongoDB and Sanctum support.
- **NestJS (`mynest`):** Node.js backend using Prisma and TypeORM, featuring WebSockets and JWT auth.
- **FastAPI (`fastapi`):** Python backend with SQLAlchemy and Alembic for migrations.
- **Express Server (`server`):** Simple Node.js backend with Prisma.

### Frontend Applications
- **Angular (`angular`):** Modern Angular 20 project.
- **Vue Projects (`myvue`, `attendance`, `claims`):** Vite-powered Vue 3 applications.
- **React Apps (`myapp`, `mfehost`, etc.):** React-based applications, often using Tailwind CSS.

---

## Building and Running

### General Workflow
Most projects follow standard framework patterns. Ensure you have the appropriate runtimes installed (Node.js, PHP, Python).

### Micro Frontends
To run the full MFE suite:
1. Start Remote Header: `cd remoteHeader && npm start`
2. Start Remote IAM: `cd remoteIAM && npm start`
3. Start Host: `cd mfehost && npm start`

### Backend Services
- **Laravel:** `cd laravel11 && php artisan serve`
- **NestJS:** `cd mynest && npm run start:dev`
- **FastAPI:** `cd fastapi && uvicorn app.main:app --reload` (Assumed main entry)
- **Express Server:** `cd server && npm run dev` (Assumed dev script)

### Frontend Apps
- **Angular:** `cd angular && npm start`
- **Vue/React (Vite):** `cd <dir> && npm run dev`

---

## Development Conventions

### Styling
- **Tailwind CSS:** Preferred for most modern projects (`myapp`, `attendance`, `claims`, `laravel11`).
- **Sass/Vanilla CSS:** Used in older or specific UI components.

### Data Access
- **Prisma:** Used in `mynest` and `server`.
- **TypeORM:** Also present in `mynest`.
- **Eloquent/MongoDB:** Used in `laravel11`.
- **SQLAlchemy:** Used in `fastapi`.

### Testing
- **Jest/Vitest:** Unit testing for Node.js/Vue.
- **Cypress:** E2E testing for Vue/Angular.
- **PHPUnit:** Testing for Laravel.

### Module Federation
- Shares `react` and `react-dom` as singletons to avoid version conflicts and reduce bundle size.
- Remote entry points are typically served as `remoteEntry.js`.

---

## Key Files
- `MFE_SETUP_GUIDE.md`: Detailed instructions for the Micro Frontend orchestration.
- `package.json` (root): Placeholder or workspace manager (if configured).
- `docker-compose.yml`: Orchestration for services (FastAPI, NestJS, etc.).
