# School Management CRM - Remote Header Micro Frontend

This is the remote header component for the School Management CRM micro frontend architecture. It provides navigation and branding for the entire application.

## Features

- Responsive navigation bar with school branding
- Navigation links to all main modules (Dashboard, Students, Teachers, Classes, Attendance)
- User profile section with logout button
- Active route highlighting
- Gradient styling for professional appearance
- Module Federation exposure as a remote component

## Development

```bash
npm install
npm start
```

Runs on http://localhost:3001

## Building for Production

```bash
npm run build
```

## Docker

```bash
docker build -t remote-header .
docker run -p 3001:3001 remote-header
```

## Module Federation

This app exposes the `Header` component for consumption by the host application. It shares common dependencies (React, React Router) as singletons to ensure version compatibility.
