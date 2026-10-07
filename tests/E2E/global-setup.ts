import { chromium, type FullConfig } from '@playwright/test';

/**
 * Log in once as wp-env's default administrator (local test credentials) and save the session.
 */
export default async function globalSetup(config: FullConfig): Promise<void> {
    const baseURL = config.projects[0].use.baseURL ?? 'http://localhost:8888';
    const browser = await chromium.launch();
    const page = await browser.newPage({ baseURL });

    await page.goto('/wp-login.php');
    await page.fill('#user_login', 'admin');
    await page.fill('#user_pass', 'password');
    await page.click('#wp-submit');
    await page.waitForURL('**/wp-admin/**');

    await page.context().storageState({ path: '.auth/admin.json' });
    await browser.close();
}
