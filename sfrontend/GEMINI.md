# Project Overview: Micro Frontend School Management CRM

This project implements a Micro Frontend (MFE) architecture using Webpack 5 Module Federation. It consists of a host application and several remote applications.

## Architecture

The system is divided into the following components:

- **Host (main_app):** The primary container that orchestrates the micro frontends.
- **Remote Header (header_app):** Provides the shared header component across the applications.
- **Remote IAM (iam_app):** Handles Identity and Access Management functionality.

## Services & Port Mapping

| Service | Container Name | Host Port | Internal Port | Description |
| :--- | :--- | :--- | :--- | :--- |
| `main_app` | `ctr_main_app` | 3000 | 3000 | Main Host Application |
| `header_app` | `ctr_header_app` | 3001 | 3000 | Remote Header Component |
| `iam_app` | `ctr_iam_app` | 3002 | 3000 | Remote IAM Application |

## Getting Started

### Prerequisites

- Docker and Docker Compose
- Node.js (for local development)

### Running with Docker

To start all services using Docker Compose:

```bash
docker-compose up -d
```

This will build and start all three applications. The main application will be accessible at `http://localhost:3000`.

### Local Development

To run services locally without Docker:

1. **Header App:**
   ```bash
   cd header_app && npm install && npm start
   ```
2. **IAM App:**
   ```bash
   cd iam_app && npm install && npm start
   ```
3. **Main App:**
   ```bash
   cd main_app && npm install && npm start
   ```

## Development Conventions

- **Technology Stack:** React 18, TypeScript, Webpack 5.
- **Module Federation:** Shared dependencies include `react` and `react-dom` as singletons.
- **Styling:** Tailwind CSS is used in some parts of the project.

## Key Files

- `docker-compose.yml`: Docker orchestration configuration.
- `main_app/webpack.config.js`: Module Federation host configuration.
- `header_app/webpack.config.js`: Module Federation remote configuration for Header.
- `iam_app/webpack.config.js`: Module Federation remote configuration for IAM.
