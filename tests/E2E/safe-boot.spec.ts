import { expect, test } from '@playwright/test';
import { forceActive, resetPlugins, takeDebugLog, wp } from './helpers';

/**
 * Phase 1 Definition of Done — the safe-boot test (ADR-0013). Each scenario is demonstrated on a
 * real WordPress, not assumed.
 */
test.describe('a plugin started through the guard never takes the site down', () => {
    test.beforeEach(() => {
        resetPlugins();
        takeDebugLog();
    });

    test('activating a plugin that needs PHP 99 is refused with a readable message', () => {
        const result = wp('plugin', 'activate', 'safe-php99');

        expect(result.code).not.toBe(0);
        expect(result.output).toContain('PHP 99.0 or newer is required');
        expect(wp('plugin', 'is-active', 'safe-php99').code).not.toBe(0);
    });

    const brokenPlugins = [
        { plugin: 'safe-php99', reason: 'PHP 99.0 or newer is required' },
        { plugin: 'safe-parse-error', reason: 'failed while starting' },
        { plugin: 'safe-boot-throws', reason: 'failed while starting' },
    ];

    for (const { plugin, reason } of brokenPlugins) {
        test(`${plugin} already active: the site, wp-admin and other plugins keep working`, async ({ page }) => {
            forceActive([`${plugin}/plugin.php`, 'safe-ok/plugin.php']);

            const front = await page.goto('/');
            expect(front?.status()).toBe(200);
            expect(await page.content()).toContain('wptoolkit-fixture:safe-ok:');
            expect(await page.content()).not.toContain(`wptoolkit-fixture:${plugin}:`);

            const admin = await page.goto('/wp-admin/');
            expect(admin?.status()).toBe(200);
            const notice = page.locator('.notice-error', { hasText: `Fixture ${plugin} has been paused` });
            await expect(notice).toBeVisible();
            await expect(notice).toContainText(reason);
        });
    }

    test('a syntax error is logged with the file that failed to parse', async ({ page }) => {
        forceActive(['safe-parse-error/plugin.php']);

        await page.goto('/');
        const log = takeDebugLog();

        expect(log).toContain('Fixture safe-parse-error could not start: ParseError');
        expect(log).toContain('broken.php');
    });

    test('a healthy plugin boots and prints its marker', async ({ page }) => {
        expect(wp('plugin', 'activate', 'safe-ok').code).toBe(0);

        await page.goto('/');

        expect(await page.content()).toContain('wptoolkit-fixture:safe-ok:');
        expect(takeDebugLog()).not.toMatch(/PHP (Fatal|Warning|Notice|Deprecated)/);
    });
});
