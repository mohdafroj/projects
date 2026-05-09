import { defineConfig } from 'vite'
import react from '@vitejs/plugin-react'
import federation from '@originjs/vite-plugin-federation';
// https://vite.dev/config/
export default defineConfig({
  plugins: [
    react(),
    federation({
      name: 'dashboard',
      filename: 'remoteEntry.js',
      exposes: {
        './Dashboard': './src/App.tsx',
      },
      shared: ['react', 'react-dom'],
      mode: 'development',
    }),
  ],
  build: {
    modulePreload: false,
    target: 'esnext',
    minify: false,
    cssCodeSplit: false,
    watch: {}, // 👈 IMPORTANT
  },
  server: {
    port: 5001,
    strictPort: true,
    cors: true,
  },
  preview: {
    port: 5001,
    strictPort: true,
  },
})
