import { chromium, type FullConfig, type Page } from '@playwright/test';

const ATTEMPTS = 4;

/**
 * Log in once as wp-env's default administrator (local test credentials) and save the session.
 *
 * A freshly started WordPress is slow on its first requests, and its first login can be rejected
 * (the test cookie isn't set yet), so warm the site up and retry instead of failing the whole run.
 */
export default async function globalSetup(config: FullConfig): Promise<void> {
    const baseURL = config.projects[0].use.baseURL ?? 'http://localhost:8888';
    const browser = await chromium.launch({ channel: config.projects[0].use.channel });
    const page = await browser.newPage({ baseURL });

    try {
        await page.goto('/', { timeout: 120_000 }); // first hit: let WordPress finish waking up
        await logIn(page);
        await page.context().storageState({ path: '.auth/admin.json' });
    } finally {
        await browser.close();
    }
}

async function logIn(page: Page): Promise<void> {
    let reason = '';
    for (let attempt = 1; attempt <= ATTEMPTS; attempt++) {
        try {
            await page.goto('/wp-login.php', { timeout: 60_000 });
            await page.fill('#user_login', 'admin');
            await page.fill('#user_pass', 'password');
            await Promise.all([page.waitForURL('**/wp-admin/**', { timeout: 45_000 }), page.click('#wp-submit')]);
            return;
        } catch (error) {
            const shown = await page.locator('#login_error').textContent({ timeout: 1_000 }).catch(() => null);
            reason = `${(error as Error).message.split('\n')[0]} | url: ${page.url()} | login error: ${shown?.trim() ?? 'none shown'}`;
            console.warn(`Login attempt ${attempt}/${ATTEMPTS} failed: ${reason}`);
        }
    }
    throw new Error(`Could not log in to WordPress after ${ATTEMPTS} attempts. Last: ${reason}`);
}
