import { defineConfig, devices } from '@playwright/test';

// The documented install path: scripts/e2e/fresh-install.mjs creates the project, starts
// "php artisan serve" and passes its address in E2E_FRESH_URL.
export default defineConfig({
    testDir: 'tests/e2e',
    testMatch: /fresh-install\.spec\.ts/,
    fullyParallel: false,
    forbidOnly: !!process.env.CI,
    retries: 0,
    workers: 1,
    timeout: 300_000,
    expect: { timeout: 15_000 },
    reporter: 'list',
    use: {
        ...devices['Desktop Chrome'],
        viewport: { width: 1440, height: 900 },
        baseURL: process.env.E2E_FRESH_URL,
        locale: 'en-US',
        trace: 'retain-on-failure',
        screenshot: 'only-on-failure',
        actionTimeout: 15_000,
        navigationTimeout: 30_000,
    },
});
