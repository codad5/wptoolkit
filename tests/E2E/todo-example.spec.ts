import { expect, test, type Page } from '@playwright/test';
import { resetPlugins, takeDebugLog, wp } from './helpers';

/**
 * Phase 5 Definition of Done, on a real WordPress with WP_DEBUG on, using examples/todo installed
 * the Composer way: admin page, settings save, meta box save and the front-end board work; nothing
 * is enqueued or translated too early (zero "called incorrectly" notices); the same screens work in
 * Arabic, right to left, with the example's own translations; and every control is reachable and
 * labelled from the keyboard.
 */

const SETTINGS = '/wp-admin/edit.php?post_type=wptk_todo&page=wptk-todo-settings';
const NEW_TODO = '/wp-admin/post-new.php?post_type=wptk_todo';
const META = (field: string) => `[name="todo_details_wptk_todo_${field}"]`;

/** Lines our plugin or WordPress's "doing it wrong" put in debug.log. */
function problems(log: string): string[] {
    return log
        .split('\n')
        .filter((line) => /incorrectly|PHP (Notice|Warning|Deprecated|Fatal)/i.test(line))
        .filter((line) => /incorrectly|wptk-todo|wptoolkit/i.test(line));
}

async function createTodo(page: Page, title: string): Promise<void> {
    await page.goto(NEW_TODO);
    await page.fill('#title', title);
    await page.selectOption(META('priority'), 'high');
    await page.fill(META('due_date'), '2026-10-09');
    await page.click('#publish');
    await expect(page.locator('#message')).toBeVisible();
}

test.describe.serial('the todo example (Phase 5 DoD)', () => {
    test.beforeAll(() => {
        resetPlugins();
        expect(wp('plugin', 'activate', 'wptk-todo').code).toBe(0);
        wp('rewrite', 'structure', '/%postname%/');
        wp('rewrite', 'flush');
        takeDebugLog();
    });

    test.afterAll(() => {
        wp('site', 'switch-language', 'en_US');
        wp('post', 'delete', ...listTodos(), '--force');
        resetPlugins();
    });

    test('admin, settings, meta box and front end work, with no notices', async ({ page }) => {
        await createTodo(page, 'Write the guide');
        await expect(page.locator(META('priority'))).toHaveValue('high');
        await expect(page.locator(META('due_date'))).toHaveValue('2026-10-09');

        await page.goto('/wp-admin/edit.php?post_type=wptk_todo');
        await expect(page.locator('td.column-todo_details_wptk_todo_priority').first()).toContainText('High');

        await page.goto(SETTINGS);
        await page.fill('[name="wptk-todo_notify_email"]', 'ops@example.test');
        await page.fill('[name="wptk-todo_sync_token"]', 'tok_123');
        await page.click('#submit');
        await expect(page.locator('#setting-error-settings_updated')).toBeVisible();
        await expect(page.locator('[name="wptk-todo_notify_email"]')).toHaveValue('ops@example.test');
        await expect(page.locator('[name="wptk-todo_sync_token"]'), 'a secret is never echoed back').toHaveValue('');

        await page.click('#submit'); // blank token: keep the saved one
        expect(wp('option', 'get', 'wptk-todo_sync_token').output.trim()).toBe('tok_123');

        const board = await page.goto('/todo-board/');
        expect(board?.status()).toBe(200);
        await expect(page.locator('.wptk-todo-board')).toContainText('Write the guide');
        await expect(page).toHaveTitle(/Todo board/);
        await expect(page.locator('link#wptk-todo-board-css')).toHaveAttribute('href', /assets\/board\.css/);

        expect(problems(takeDebugLog())).toEqual([]);
    });

    test('logged-out visitors are sent to log in from the board', async ({ browser }) => {
        const context = await browser.newContext({ storageState: { cookies: [], origins: [] } });
        const page = await context.newPage();

        await page.goto('/todo-board/');

        await expect(page).toHaveURL(/wp-login\.php\?redirect_to=/);
        await context.close();
    });

    test('Arabic: screens mirror right to left and use the example’s translations', async ({ page }) => {
        expect(wp('language', 'core', 'install', 'ar').code).toBe(0);
        expect(wp('site', 'switch-language', 'ar').code).toBe(0);

        await page.goto(SETTINGS);
        await expect(page.locator('html')).toHaveAttribute('dir', 'rtl');
        await expect(page.locator('.wrap h1')).toHaveText('إعدادات المهام');
        await expect(page.locator('label[for="wptk-todo-setting-default_priority"]')).toHaveText('الأولوية الافتراضية');

        await page.goto(NEW_TODO);
        await expect(page.locator('#wptk-todo-box-todo_details h2')).toContainText('تفاصيل المهمة');

        await page.goto('/todo-board/');
        await expect(page.locator('html')).toHaveAttribute('dir', 'rtl');
        await expect(page.locator('#wptk-todo-board-title')).toHaveText('لوحة المهام');
        // With 'rtl' => 'replace', WordPress swaps the file and gives the tag the id {handle}-rtl-css.
        await expect(page.locator('link#wptk-todo-board-rtl-css')).toHaveAttribute('href', /board-rtl\.css/);

        expect(problems(takeDebugLog())).toEqual([]);
        wp('site', 'switch-language', 'en_US');
    });

    for (const [screen, url, scope] of [
        ['settings page', SETTINGS, 'form[action="options.php"]'],
        ['todo edit screen', NEW_TODO, '#wptk-todo-box-todo_details'],
    ] as const) {
        test(`keyboard only: every control on the ${screen} is labelled and reachable`, async ({ page }) => {
            await page.goto(url);
            const controls = page.locator(`${scope} :is(input, select, textarea, button):not([type="hidden"]):visible`);
            const count = await controls.count();
            expect(count).toBeGreaterThan(1);

            // Tag every control so focus can be matched even when it has no name or id (WordPress's
            // own meta box buttons don't).
            const labels: string[] = [];
            for (let i = 0; i < count; i++) {
                const control = controls.nth(i);
                await expect(control).toHaveAccessibleName(/\S/);
                await control.evaluate((el, n) => el.setAttribute('data-kbd', String(n)), i);
                labels.push(`${i}:${(await control.getAttribute('name')) ?? (await control.getAttribute('class')) ?? ''}`);
            }

            // Start on the first control, then reach every other one with Tab alone.
            await controls.first().focus();
            const reached = new Set<number>([0]);
            for (let presses = 0; presses < 60 && reached.size < count; presses++) {
                await page.keyboard.press('Tab');
                const tag = await page.evaluate(() => document.activeElement?.getAttribute('data-kbd') ?? null);
                if (tag !== null) {
                    reached.add(Number(tag));
                }
            }
            const missed = labels.filter((_, i) => !reached.has(i));
            expect(missed, 'controls Tab never reached').toEqual([]);
        });
    }
});

function listTodos(): string[] {
    const out = wp('post', 'list', '--post_type=wptk_todo', '--post_status=any', '--format=ids').output.trim();
    return out === '' ? ['0'] : out.split(/\s+/);
}
