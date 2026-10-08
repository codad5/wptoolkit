<?php

/**
 * @license GPL-2.0-or-later
 */

declare(strict_types=1);

namespace Codad5\WPToolkit\Foundation;

use Closure;
use Throwable;

/**
 * Adds WordPress actions and filters for one plugin and remembers every one, so the plugin can
 * list them (`hooks:list`) and remove all of them on deactivation. Replaces 0.x's
 * `add_tracked_action()`.
 *
 * In containment mode (ADR-0013) each callback is wrapped: an exception is reported and swallowed,
 * an action simply stops, and a filter returns the value it was given — one broken callback can't
 * take the page down. On by default in production, off on local/development sites with WP_DEBUG.
 */
final class HookRegistrar
{
    /**
     * @var list<array{type: 'action'|'filter', hook: string, callback: callable, registered: callable, priority: int, args: int}>
     */
    private array $hooks = [];

    private Closure $report;

    /**
     * @param bool $contain Wrap callbacks so exceptions are reported instead of thrown.
     * @param (Closure(Throwable, string): void)|null $report Called with the exception and the hook
     *        name; defaults to error_log().
     */
    public function __construct(private readonly bool $contain = false, ?Closure $report = null)
    {
        $this->report = $report ?? static function (Throwable $error, string $hook): void {
            error_log(sprintf(
                '[WPToolkit] Callback on "%s" failed: %s: %s in %s:%d',
                $hook,
                $error::class,
                $error->getMessage(),
                $error->getFile(),
                $error->getLine()
            ));
        };
    }

    public function addAction(string $hook, callable $callback, int $priority = 10, int $acceptedArgs = 1): void
    {
        $registered = $this->contain ? $this->wrap('action', $hook, $callback) : $callback;
        add_action($hook, $registered, $priority, $acceptedArgs);
        $this->remember('action', $hook, $callback, $registered, $priority, $acceptedArgs);
    }

    public function addFilter(string $hook, callable $callback, int $priority = 10, int $acceptedArgs = 1): void
    {
        $registered = $this->contain ? $this->wrap('filter', $hook, $callback) : $callback;
        add_filter($hook, $registered, $priority, $acceptedArgs);
        $this->remember('filter', $hook, $callback, $registered, $priority, $acceptedArgs);
    }

    /**
     * Remove every hook this registrar added, newest first.
     */
    public function removeAll(): void
    {
        foreach (array_reverse($this->hooks) as $hook) {
            if ($hook['type'] === 'action') {
                remove_action($hook['hook'], $hook['registered'], $hook['priority']);
            } else {
                remove_filter($hook['hook'], $hook['registered'], $hook['priority']);
            }
        }

        $this->hooks = [];
    }

    /**
     * Every hook currently registered through this registrar, oldest first. `callback` is what the
     * plugin passed; `registered` is what WordPress holds (a wrapper in containment mode).
     *
     * @return list<array{type: 'action'|'filter', hook: string, callback: callable, registered: callable, priority: int, args: int}>
     */
    public function all(): array
    {
        return $this->hooks;
    }

    public function isContaining(): bool
    {
        return $this->contain;
    }

    /**
     * @param 'action'|'filter' $type
     */
    private function wrap(string $type, string $hook, callable $callback): Closure
    {
        $report = $this->report;

        return static function (mixed ...$args) use ($type, $hook, $callback, $report): mixed {
            try {
                return $callback(...$args);
            } catch (Throwable $error) {
                $report($error, $hook);
                return $type === 'filter' ? ($args[0] ?? null) : null;
            }
        };
    }

    /**
     * @param 'action'|'filter' $type
     */
    private function remember(string $type, string $hook, callable $callback, callable $registered, int $priority, int $acceptedArgs): void
    {
        $this->hooks[] = [
            'type' => $type,
            'hook' => $hook,
            'callback' => $callback,
            'registered' => $registered,
            'priority' => $priority,
            'args' => $acceptedArgs,
        ];
    }
}
