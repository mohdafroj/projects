# Micro Frontend Setup Guide

This guide explains how to run the Host and Remote micro frontend applications together.

## Architecture Overview

- **Host App** (Port 3000): The main application that consumes remote components
- **Remote Header App** (Port 3001): A module-federated component that is exposed to the host
- **Remote IAM App** (Port 3002): A module-federated component that is exposed to the host

## Quick Start

### Step 1: Install Dependencies

#### Host App:
```bash
cd mfehost
npm install
```

#### Remote Header App:
```bash
cd remoteHeader
npm install
```

#### Remote IAM App:
```bash
cd remoteIAM
npm install
```

### Step 2: Run Both Applications

**Terminal 1 - Start Remote Header App:**
```bash
cd remoteHeader
npm start
```
The remote header app will run on http://localhost:3001

**Terminal 2 - Start Host App:**
```bash
cd mfehost
npm start
```
The host app will run on http://localhost:3000

**Terminal 3 - Start Remote IAM App:**
```bash
cd remoteIAM
npm start
```
The Remote IAM app will run on http://localhost:3002

### Step 3: View the Application

Open your browser and go to:
```
http://localhost:3000
```

You should see:
- The Host application heading
- The Remote Header component loaded inside the host
- The remote component has a blue border and displays "Remote Header Component"
- The Remote IAM component loaded inside the host

## How It Works

1. **Module Federation**: Both apps use Webpack 5's Module Federation feature
2. **Remote Entry**: When the remote app starts, it generates `remoteEntry.js` at `http://localhost:3001/remoteEntry.js`
3. **Dynamic Import**: The host app uses `React.lazy()` to dynamically import `remoteHeader/Header`
4. **Shared Dependencies**: React and ReactDOM are shared to avoid duplication

## File Structure

```
mfehost/
  ├── src/
  │   ├── App.tsx         (Consumes remote app)
  │   └── index.tsx
  ├── webpack.config.js  (Configured as host)
  └── package.json

remoteHeader/
  ├── src/
  │   ├── Header.tsx         (Exposed component)
  │   └── index.tsx
  ├── webpack.config.js  (Configured as remote)
  └── package.json
```

## Adding More Remote Apps

To add additional remote apps:

1. Create a new app with a similar structure to `remote-app`
2. In its `webpack.config.js`, set a unique `name` and `filename`:
   ```js
   new ModuleFederationPlugin({
     name: "newRemote",
     filename: "remoteEntry.js",
     exposes: {
       "./App": "./src/App.js",
     },
     shared: ["react", "react-dom"],
   })
   ```

3. Run it on a different port (e.g., 3002)

4. In the host's `webpack.config.js`, add the new remote:
   ```js
   remotes: {
     remoteApp: "remoteApp@http://localhost:3001/remoteEntry.js",
     newRemote: "newRemote@http://localhost:3002/remoteEntry.js"
   }
   ```

5. In the host's `App.js`, lazy load and use the new component:
   ```js
   const NewRemote = React.lazy(() => import('newRemote/App'));
   ```

## Build & Production

### Build Host:
```bash
cd mfehost
npm run build
```

### Build Remote:
```bash
cd remoteHeader
npm run build
```

Built files will be in the `dist/` directory of each app.

## Troubleshooting

| Issue | Solution |
|-------|----------|
| Remote app not loading | Ensure remote app is running on port 3001 |
| Port already in use | Change port in `webpack.config.js` devServer |
| Module not found error | Check remoteEntry.js URL and remote name |
| Shared dependency mismatch | Ensure both apps use same React/ReactDOM versions |

## Next Steps

- Add more remote applications
- Configure different ports for multiple remotes
- Set up production builds and deployments
- Add error boundaries for better error handling
- Implement shared state management between apps
