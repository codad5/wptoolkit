import { expect, test } from '@playwright/test';
import { forceActive, resetPlugins, takeDebugLog, toolkitVersion, wp } from './helpers';

/**
 * Phase 1 Definition of Done — the coexistence test (ADR-0005, ADR-0020): two plugins bundling two
 * versions of WPToolkit on one site.
 */
test.describe('two plugins, two WPToolkit versions, one site', () => {
    test.beforeEach(() => {
        resetPlugins();
        takeDebugLog();
    });

    test('two scoped copies of different versions both run, with no warnings', async ({ page }) => {
        expect(wp('plugin', 'activate', 'coexist-alpha').code).toBe(0);
        expect(wp('plugin', 'activate', 'coexist-beta').code).toBe(0);

        const front = await page.goto('/');
        expect(front?.status()).toBe(200);
        const html = await page.content();

        expect(html).toContain(`wptoolkit-fixture:coexist-alpha:${toolkitVersion()}`);
        expect(html).toContain('wptoolkit-fixture:coexist-beta:1.4.0');
        expect(takeDebugLog()).not.toMatch(/PHP (Fatal|Warning|Notice|Deprecated)/);
    });

    test('wp {slug} toolkit:info lists both copies, each plugin with its own command (Phase 6 DoD)', () => {
        expect(wp('plugin', 'activate', 'coexist-alpha').code).toBe(0);
        expect(wp('plugin', 'activate', 'coexist-beta').code).toBe(0);

        for (const [command, own] of [['coexist-alpha', 'Alpha\\WPToolkit'], ['coexist-beta', 'Beta\\WPToolkit']] as const) {
            const result = wp(command, 'toolkit:info', '--format=json');
            expect(result.code, result.output).toBe(0);
            expect(result.output).toContain(`(namespace ${own})`);

            const copies = JSON.parse(result.output.slice(result.output.indexOf('['))) as Array<Record<string, string>>;
            expect(copies.map((c) => c.namespace).sort()).toEqual(['Alpha\\WPToolkit', 'Beta\\WPToolkit']);
            expect(copies.map((c) => c.version).sort()).toEqual([toolkitVersion(), '1.4.0'].sort());
            expect(copies.find((c) => c.namespace === own)?.['this plugin']).toBe('yes');
        }
    });

    test('activating an unscoped plugin that needs a newer WPToolkit than the loaded copy is refused', () => {
        expect(wp('plugin', 'activate', 'unscoped-first').code).toBe(0);

        const result = wp('plugin', 'activate', 'unscoped-second');

        expect(result.code).not.toBe(0);
        expect(result.output).toContain('WPToolkit ^2.0 is required');
        expect(result.output).toContain('unscoped-first');
        expect(wp('plugin', 'is-active', 'unscoped-second').code).not.toBe(0);
    });

    test('an incompatible unscoped plugin that is already active stays inert and names the copy that won', async ({ page }) => {
        forceActive(['unscoped-first/plugin.php', 'unscoped-second/plugin.php']);

        const front = await page.goto('/');
        expect(front?.status()).toBe(200);
        const html = await page.content();
        expect(html).toContain('wptoolkit-fixture:unscoped-first:');
        expect(html).not.toContain('wptoolkit-fixture:unscoped-second:');

        await page.goto('/wp-admin/');
        const notice = page.locator('.notice-error', { hasText: 'Fixture unscoped-second has been paused' });
        await expect(notice).toBeVisible();
        await expect(notice).toContainText('unscoped-first');
        expect(takeDebugLog()).not.toMatch(/PHP Fatal/);
    });
});
