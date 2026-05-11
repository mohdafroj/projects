# Remote Micro Frontend React App

This is a remote micro frontend app that exposes components via Webpack Module Federation to be consumed by a host application.

## Setup

1. Install dependencies:
   ```
   npm install
   ```

2. Start the development server:
   ```
   npm start
   ```

   The app will run on http://localhost:3001

## Building

To build for production:
```
npm run build
```

To serve the built app:
```
npm run serve
```

## Exposed Components

This remote app exposes the following:
- **App**: The main App component available as `remoteApp/App`

## Connecting to Host

To connect this remote app to the host application:

1. Update the host's `webpack.config.js` to add this remote:
   ```js
   remotes: {
     remoteApp: "remoteApp@http://localhost:3001/remoteEntry.js"
   }
   ```

2. In the host app, import the remote component:
   ```js
   const RemoteApp = React.lazy(() => import('remoteApp/App'));
   ```

3. Use `<Suspense>` to handle loading states when rendering the component.

## Notes

- The remote app generates a `remoteEntry.js` file when built/served that the host can consume.
- Shared dependencies (react, react-dom) are configured to be shared between host and remote.
- Both apps should ideally use the same versions of shared dependencies.
