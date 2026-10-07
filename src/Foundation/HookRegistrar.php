<?php

/**
 * @license GPL-2.0-or-later
 */

declare(strict_types=1);

namespace Codad5\WPToolkit\Foundation;

/**
 * Adds WordPress actions and filters for one plugin and remembers every one, so the plugin can
 * list them (`hooks:list`) and remove all of them on deactivation. Replaces 0.x's
 * `add_tracked_action()`.
 */
final class HookRegistrar
{
    /** @var list<array{type: 'action'|'filter', hook: string, callback: callable, priority: int, args: int}> */
    private array $hooks = [];

    public function addAction(string $hook, callable $callback, int $priority = 10, int $acceptedArgs = 1): void
    {
        add_action($hook, $callback, $priority, $acceptedArgs);
        $this->remember('action', $hook, $callback, $priority, $acceptedArgs);
    }

    public function addFilter(string $hook, callable $callback, int $priority = 10, int $acceptedArgs = 1): void
    {
        add_filter($hook, $callback, $priority, $acceptedArgs);
        $this->remember('filter', $hook, $callback, $priority, $acceptedArgs);
    }

    /**
     * Remove every hook this registrar added, newest first.
     */
    public function removeAll(): void
    {
        foreach (array_reverse($this->hooks) as $hook) {
            if ($hook['type'] === 'action') {
                remove_action($hook['hook'], $hook['callback'], $hook['priority']);
            } else {
                remove_filter($hook['hook'], $hook['callback'], $hook['priority']);
            }
        }

        $this->hooks = [];
    }

    /**
     * @param 'action'|'filter' $type
     */
    private function remember(string $type, string $hook, callable $callback, int $priority, int $acceptedArgs): void
    {
        $this->hooks[] = [
            'type' => $type,
            'hook' => $hook,
            'callback' => $callback,
            'priority' => $priority,
            'args' => $acceptedArgs,
        ];
    }

    /**
     * Every hook currently registered through this registrar, oldest first.
     *
     * @return list<array{type: 'action'|'filter', hook: string, callback: callable, priority: int, args: int}>
     */
    public function all(): array
    {
        return $this->hooks;
    }
}
