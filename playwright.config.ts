import { defineConfig } from '@playwright/test';

/**
 * End-to-end tests against a real WordPress started with `npm run e2e:start` (wp-env, port 8888).
 * Tests change site state (active plugins), so they run serially.
 */
export default defineConfig({
    testDir: './tests/E2E',
    testMatch: '**/*.spec.ts',
    fullyParallel: false,
    workers: 1,
    retries: process.env.CI ? 1 : 0,
    timeout: 60_000,
    reporter: process.env.CI ? [['github'], ['list']] : 'list',
    globalSetup: './tests/E2E/global-setup.ts',
    use: {
        baseURL: process.env.WP_BASE_URL ?? 'http://localhost:8888',
        storageState: '.auth/admin.json',
        trace: 'retain-on-failure',
        // CI uses the Google Chrome preinstalled on GitHub's runners: no browser download and no
        // apt install of its dependencies (Azure's apt mirror stalls and fails jobs).
        ...(process.env.CI ? { channel: 'chrome' } : {}),
    },
});
