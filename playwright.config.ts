import { execFileSync } from 'node:child_process';
import { defineConfig, devices } from '@playwright/test';

// End to end tests: every project gets its own server with a fresh, throwaway install, and runs one journey.
// The Easy mode journey runs at 1440 and 390 pixels, the builder journey at 1440, 1024 and 390.
const computer = {
    ...devices['Desktop Chrome'],
    viewport: { width: 1440, height: 900 },
};
const tablet = {
    ...devices['Desktop Chrome'],
    viewport: { width: 1024, height: 768 },
    hasTouch: true,
};
const phone = {
    ...devices['Desktop Chrome'],
    viewport: { width: 390, height: 844 },
    deviceScaleFactor: 3,
    isMobile: true,
    hasTouch: true,
};

const projects = [
    { name: 'desktop', testMatch: /easy-journey/, use: computer },
    { name: 'phone', testMatch: /easy-journey/, use: phone },
    { name: 'builder-desktop', testMatch: /builder-landing/, use: computer },
    { name: 'builder-tablet', testMatch: /builder-landing/, use: tablet },
    { name: 'builder-phone', testMatch: /builder-landing/, use: phone },
] as const;

const freePortScript =
    "const s=require('net').createServer();s.listen(0,'127.0.0.1',()=>{process.stdout.write(String(s.address().port));s.close()})";

// Ports are picked once in the main process; workers read them back from the environment.
function portFor(name: string): string {
    const key = `E2E_PORT_${name.toUpperCase()}`;

    process.env[key] ??= execFileSync(process.execPath, [
        '-e',
        freePortScript,
    ]).toString();

    return process.env[key];
}

const host = '127.0.0.1';

export default defineConfig({
    testDir: 'tests/e2e',
    fullyParallel: false,
    forbidOnly: !!process.env.CI,
    retries: 0,
    workers: projects.length,
    timeout: 240_000,
    expect: { timeout: 15_000 },
    reporter: process.env.CI ? [['list'], ['html', { open: 'never' }]] : 'list',
    use: {
        locale: 'en-US',
        timezoneId: 'Europe/Lisbon',
        trace: 'retain-on-failure',
        screenshot: 'only-on-failure',
        actionTimeout: 15_000,
        navigationTimeout: 30_000,
    },
    projects: projects.map((project) => ({
        ...project,
        use: {
            ...project.use,
            baseURL: `http://${host}:${portFor(project.name)}`,
        },
    })),
    webServer: projects.map((project) => ({
        command: `node scripts/e2e/serve.mjs ${project.name} ${portFor(project.name)}`,
        url: `http://${host}:${portFor(project.name)}/up`,
        reuseExistingServer: false,
        timeout: 60_000,
        stdout: 'ignore' as const,
        stderr: 'pipe' as const,
        // SIGTERM lets the serve script remove its throwaway folder.
        gracefulShutdown: { signal: 'SIGTERM' as const, timeout: 5_000 },
    })),
});
