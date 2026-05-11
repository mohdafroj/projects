# Host Micro Frontend React App

This is a host application for microfrontends using React and Webpack Module Federation.

## Setup

1. Install dependencies:
   ```
   npm install
   ```

2. Start the development server:
   ```
   npm start
   ```

   The app will run on http://localhost:3000

## Running with Remote Apps

To see the full micro frontend setup:

1. **Start the Remote App** (in a separate terminal):
   ```
   cd ../remote-app
   npm install
   npm start
   ```
   The remote app runs on http://localhost:3001

2. **Start the Host App** (in another terminal):
   ```
   npm start
   ```
   The host app runs on http://localhost:3000

3. Open http://localhost:3000 in your browser. The host will automatically consume and display the remote app.

## Building

To build for production:
```
npm run build
```

To serve the built app:
```
npm run serve
```

## Configuration

- **Remotes**: Configured in `webpack.config.js` under the `remotes` object in the ModuleFederationPlugin. Currently configured to consume:
  - `remoteApp` from `http://localhost:3001/remoteEntry.js`

- **Shared Dependencies**: React and ReactDOM are shared by default to avoid duplication.

## Usage

- Remote components are lazy-loaded using dynamic imports.
- `<Suspense>` is used to handle loading states while remote components are being fetched.

## Troubleshooting

- **Module not found**: Ensure the remote app is running on port 3001 and the URL is correct.
- **Shared dependencies mismatch**: Both apps should use the same versions of React and ReactDOM.
- **CORS issues**: Dev servers typically allow cross-origin requests by default.
- **Port conflicts**: If port 3000 or 3001 is already in use, modify the `devServer.port` in `webpack.config.js`.