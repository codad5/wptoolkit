import { execFileSync } from 'node:child_process';
import { readFileSync } from 'node:fs';
import { join } from 'node:path';

export interface CliResult {
    code: number;
    output: string;
}

/**
 * Run WP-CLI inside the wp-env container. Arguments are passed as an array, so nothing is
 * re-quoted by a shell.
 */
export function wp(...args: string[]): CliResult {
    const npx = process.platform === 'win32' ? 'npx.cmd' : 'npx';
    try {
        const output = execFileSync(npx, ['wp-env', 'run', 'cli', '--', 'wp', ...args], {
            encoding: 'utf8',
            stdio: ['ignore', 'pipe', 'pipe'],
            shell: process.platform === 'win32',
        });
        return { code: 0, output };
    } catch (error) {
        const failure = error as { status?: number; stdout?: string; stderr?: string };
        return { code: failure.status ?? 1, output: `${failure.stdout ?? ''}${failure.stderr ?? ''}` };
    }
}

/** Deactivate everything, without running anything's deactivation hook twice. */
export function resetPlugins(): void {
    forceActive([]);
}

/**
 * Mark plugins active directly, as if they had been active before their requirements stopped
 * being met — bypasses activation hooks on purpose.
 */
export function forceActive(plugins: string[]): void {
    const result = wp('eval-file', 'wp-content/e2e-bin/force-active.php', ...plugins);
    if (result.code !== 0) {
        throw new Error(`force-active failed: ${result.output}`);
    }
}

/** Return and clear wp-content/debug.log. */
export function takeDebugLog(): string {
    return wp('eval-file', 'wp-content/e2e-bin/debug-log.php').output;
}

/** The library's current version, read from the source the fixtures were built from. */
export function toolkitVersion(): string {
    const source = readFileSync(join(__dirname, '../../src/Foundation/Application.php'), 'utf8');
    const match = source.match(/VERSION = '([^']+)'/);
    if (!match) {
        throw new Error('Application::VERSION not found');
    }
    return match[1];
}
