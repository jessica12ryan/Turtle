import { defineConfig, devices } from '@playwright/test';

export default defineConfig({
  testDir: './tests',
  timeout: 90_000,
  expect: { timeout: 15_000 },
  fullyParallel: false,
  workers: 1,
  retries: 0,
  reporter: [
    ['list'],
    ['html', { open: 'never', outputFolder: 'playwright-report' }],
  ],
  outputDir: 'test-results',
  use: {
    // Set by docker-compose.e2e.yml to the internal app service name.
    baseURL: process.env.BASE_URL ?? 'http://localhost:8085',
    ...devices['Desktop Chrome'],
    trace: 'on-first-retry',
    screenshot: 'only-on-failure',
    video: 'retain-on-failure',
  },
});
