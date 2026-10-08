<?php

/**
 * @license GPL-2.0-or-later
 */

declare(strict_types=1);

namespace Codad5\WPToolkit\Adapters\Migrations;

use Codad5\WPToolkit\Contracts\Data\MigrationStore;
use Codad5\WPToolkit\Foundation\Identity;

/**
 * Migration bookkeeping in options (ADR-0018):
 *
 * - `{slug}_migrations` — applied id => finished at (autoloaded: admin_init checks it every request);
 * - `{slug}_migrations_state` — batch progress and the last failure;
 * - `{slug}_migrations_lock` — the lock's expiry. add_option() is an INSERT that fails when the row
 *   exists, which makes taking the lock atomic.
 */
final class OptionMigrationStore implements MigrationStore
{
    private readonly string $appliedOption;

    private readonly string $stateOption;

    private readonly string $lockOption;

    public function __construct(Identity $identity)
    {
        $this->appliedOption = $identity->optionKey('migrations');
        $this->stateOption = $identity->optionKey('migrations_state');
        $this->lockOption = $identity->optionKey('migrations_lock');
    }

    public function applied(): array
    {
        $applied = get_option($this->appliedOption, []);
        if (!is_array($applied)) {
            return [];
        }

        $clean = [];
        foreach ($applied as $id => $at) {
            $clean[(string) $id] = is_numeric($at) ? (int) $at : 0;
        }

        return $clean;
    }

    public function markApplied(string $id, int $at): void
    {
        $applied = $this->applied();
        $applied[$id] = $at;
        update_option($this->appliedOption, $applied, true);

        $state = $this->state();
        unset($state['progress'][$id]);
        $this->saveState($state);
    }

    public function forget(string $id): void
    {
        $applied = $this->applied();
        unset($applied[$id]);
        update_option($this->appliedOption, $applied, true);

        $state = $this->state();
        unset($state['progress'][$id]);
        $this->saveState($state);
    }

    public function progress(string $id): int
    {
        return $this->state()['progress'][$id] ?? 0;
    }

    public function setProgress(string $id, int $batches): void
    {
        $state = $this->state();
        $state['progress'][$id] = $batches;
        $this->saveState($state);
    }

    public function failure(): ?array
    {
        return $this->state()['failure'];
    }

    public function setFailure(?array $failure): void
    {
        $state = $this->state();
        $state['failure'] = $failure;
        $this->saveState($state);
    }

    public function acquireLock(int $now, int $ttl): bool
    {
        if (add_option($this->lockOption, $now + $ttl, '', false)) {
            return true;
        }

        $expires = get_option($this->lockOption);
        if (is_numeric($expires) && (int) $expires > $now) {
            return false;
        }

        // Expired (a run died without releasing it): take it over. Two takers race on add_option().
        delete_option($this->lockOption);

        return add_option($this->lockOption, $now + $ttl, '', false);
    }

    public function releaseLock(): void
    {
        delete_option($this->lockOption);
    }

    /**
     * @return array{progress: array<string, int>, failure: array{id: string, message: string, at: int}|null}
     */
    private function state(): array
    {
        $state = get_option($this->stateOption, []);
        $progress = is_array($state) && isset($state['progress']) && is_array($state['progress']) ? $state['progress'] : [];
        $failure = is_array($state) && isset($state['failure']['id'], $state['failure']['message'], $state['failure']['at'])
            ? ['id' => (string) $state['failure']['id'], 'message' => (string) $state['failure']['message'], 'at' => (int) $state['failure']['at']]
            : null;

        return [
            'progress' => array_map('intval', array_filter($progress, 'is_numeric')),
            'failure' => $failure,
        ];
    }

    /**
     * @param array{progress: array<string, int>, failure: array{id: string, message: string, at: int}|null} $state
     */
    private function saveState(array $state): void
    {
        update_option($this->stateOption, $state, false);
    }
}
