<?php

/**
 * @license GPL-2.0-or-later
 */

declare(strict_types=1);

namespace Codad5\WPToolkit\Data\Migrations;

use Codad5\WPToolkit\Foundation\HookRegistrar;
use Codad5\WPToolkit\Foundation\Identity;

/**
 * When migrations run in WordPress (ADR-0018): on activation, and on `admin_init` whenever one is
 * pending — each with a short time budget. Unfinished batched work continues on WP-Cron until done.
 * A failure shows administrators a notice until a later run succeeds.
 */
final class MigrationRunner
{
    /** Seconds of batched work per admin page load or activation. */
    public const WEB_BUDGET = 10;

    /** Seconds of batched work per cron run. */
    public const CRON_BUDGET = 20;

    private readonly string $cronHook;

    public function __construct(private readonly Migrator $migrator, Identity $identity)
    {
        $this->cronHook = $identity->cronHook('migrate');
    }

    public function register(HookRegistrar $hooks): void
    {
        $hooks->addAction('admin_init', [$this, 'runIfPending']);
        $hooks->addAction($this->cronHook, [$this, 'runFromCron']);
        $hooks->addAction('admin_notices', [$this, 'printFailure']);
    }

    /**
     * @internal Hooked to admin_init; also called on activation.
     */
    public function runIfPending(): void
    {
        if ($this->migrator->needsRun()) {
            $this->continueLater($this->migrator->run(self::WEB_BUDGET));
        }
    }

    /**
     * @internal Hooked to the `{slug}_migrate` cron event.
     */
    public function runFromCron(): void
    {
        $this->continueLater($this->migrator->run(self::CRON_BUDGET));
    }

    /**
     * @internal Hooked to admin_notices.
     */
    public function printFailure(): void
    {
        $failure = $this->migrator->lastFailure();
        if ($failure === null || !current_user_can('manage_options')) {
            return;
        }

        printf(
            '<div class="notice notice-error"><p>%s</p><p><code>%s</code>: %s</p></div>',
            esc_html__(
                'A data update failed and was stopped. The site keeps working with the data as it was; the update retries on the next admin page load.',
                'wptoolkit'
            ),
            esc_html($failure['id']),
            esc_html($failure['message'])
        );
    }

    public function cronHook(): string
    {
        return $this->cronHook;
    }

    private function continueLater(MigrationResult $result): void
    {
        if (($result->incomplete !== null || $result->locked) && wp_next_scheduled($this->cronHook) === false) {
            wp_schedule_single_event(time() + 30, $this->cronHook);
        }
    }
}
