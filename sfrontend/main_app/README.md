# School Management CRM - Micro Frontend Host App

This is the host application for a School Management CRM built with Module Federation. It serves as the container for remote micro frontend applications.

## Architecture

- **Host App** (mfehost): Runs on port 3000
- **Remote Header** (remoteHeader): Runs on port 3001
- **Technology**: React 18, TypeScript, Webpack 5, Module Federation

## Features

- Dashboard with quick links to key modules
- Students Management
- Teachers Management
- Classes Management
- Attendance Tracking
- Integration with remote micro frontends

## Development

### Using Docker Compose (Recommended)

```bash
docker compose up
```

This starts both the host app (port 3000) and remote header app (port 3001) with hot reload enabled.

### Local Development

```bash
npm install
npm start
```

Visit http://localhost:3000

## Building for Production

```bash
npm run build
```

## Docker Build

```bash
docker build -t school-management-host .
docker run -p 3000:3000 school-management-host
```

## Module Federation Configuration

The host app exposes the App component and remotely consumes the Header component from the remoteHeader micro frontend. Shared dependencies (React, React Router) are configured as singletons to prevent version conflicts.
