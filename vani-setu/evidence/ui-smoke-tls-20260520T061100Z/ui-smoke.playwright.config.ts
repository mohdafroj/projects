import { defineConfig, devices } from '@playwright/test'

export default defineConfig({
  testDir: '.',
  testMatch: ['ui-smoke.spec.ts'],
  timeout: 120000,
  fullyParallel: false,
  use: {
    ...devices['Desktop Chrome'],
    ignoreHTTPSErrors: true,
    trace: 'retain-on-failure',
    launchOptions: {
      args: ['--host-resolver-rules=MAP vanisetu.rajyasabha.digital 127.0.0.1']
    }
  }
})
