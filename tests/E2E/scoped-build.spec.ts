import { expect, test } from '@playwright/test';
import { resetPlugins, takeDebugLog, wp } from './helpers';

/**
 * A Composer-based plugin, written against Codad5\WPToolkit and packaged with Build::scope(), on a
 * real WordPress. The library, the plugin's own code and Composer's autoload map must all have been
 * moved to the scoped namespace, or activation is a fatal error (the fixture is built by
 * build-fixtures.php with the real packager).
 */
test.describe.serial('a plugin packaged with Build::scope()', () => {
    test.beforeAll(() => {
        resetPlugins();
        takeDebugLog();
    });

    test.afterAll(() => {
        resetPlugins();
    });

    test('activates and runs from the scoped namespace only', async ({ page }) => {
        const activated = wp('plugin', 'activate', 'wptk-todo-scoped');
        expect(activated.code, activated.output).toBe(0);

        const info = wp('wptk-todo', 'toolkit:info', '--format=json');
        expect(info.code, info.output).toBe(0);
        expect(info.output).toContain('(namespace WptkTodo\\Lib\\WPToolkit)');
        expect(info.output).not.toContain('Codad5\\\\WPToolkit');

        // An admin page rendered by the plugin's provider through the scoped library.
        const settings = await page.goto('/wp-admin/edit.php?post_type=wptk_todo&page=wptk-todo-settings');
        expect(settings?.status()).toBe(200);
        await expect(page.locator('[name="wptk-todo_notify_email"]')).toBeVisible();

        // A route registered by the plugin's own controller class.
        const routes = wp('wptk-todo', 'routes:list');
        expect(routes.code, routes.output).toBe(0);
        expect(routes.output).toContain('todos');

        expect(takeDebugLog()).not.toMatch(/PHP (Fatal|Warning|Notice|Deprecated)/);
    });

    test('scaffolding inside the scoped plugin writes the scoped namespace', () => {
        expect(wp('plugin', 'activate', 'wptk-todo-scoped').code).toBe(0);

        // The fixture has no composer.json namespace to read, so name the plugin's namespace explicitly.
        const made = wp('eval', `
            $s = new \\WptkTodo\\Lib\\WPToolkit\\Cli\\Scaffolder(
                sys_get_temp_dir() . '/wptk-scaffold-e2e', 'WptkTodo', 'wptk-todo', 'wptk-todo',
                WP_PLUGIN_DIR . '/wptk-todo-scoped/vendor/codad5/wptoolkit/resources/stubs',
                new \\WptkTodo\\Lib\\WPToolkit\\Adapters\\Clock\\SystemClock()
            );
            echo file_get_contents($s->make('controller', 'Report', true));
        `);

        expect(made.code, made.output).toBe(0);
        expect(made.output).toContain('use WptkTodo\\Lib\\WPToolkit\\Http\\Request;');
        expect(made.output).not.toContain('Codad5\\WPToolkit');
    });
});
