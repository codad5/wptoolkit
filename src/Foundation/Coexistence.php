<?php

/**
 * @license GPL-2.0-or-later
 */

declare(strict_types=1);

namespace Codad5\WPToolkit\Foundation;

/**
 * The coexistence ledger: every copy of WPToolkit loaded on the site records itself here, so a
 * plugin can see which copies exist (`toolkit:info`, diagnostics) and explain a conflict.
 *
 * This is the one global ADR-0005 allows. Its key is a plain string on purpose: scoping tools
 * rewrite namespaces, not strings, so every scoped copy writes to the same ledger.
 */
final class Coexistence
{
    public const LEDGER = '__wptoolkit_copies';

    /**
     * Record a copy. Recording the same path again just refreshes it.
     */
    public static function record(string $version, string $path, string $namespace): void
    {
        if (!isset($GLOBALS[self::LEDGER]) || !is_array($GLOBALS[self::LEDGER])) {
            $GLOBALS[self::LEDGER] = [];
        }

        $GLOBALS[self::LEDGER][$path] = ['version' => $version, 'path' => $path, 'namespace' => $namespace];
    }

    /**
     * Every copy recorded so far, keyed by path.
     *
     * @return array<string, array{version: string, path: string, namespace: string}>
     */
    public static function copies(): array
    {
        $ledger = $GLOBALS[self::LEDGER] ?? [];

        return is_array($ledger) ? $ledger : [];
    }

    /**
     * The package directory of the copy this class belongs to.
     */
    public static function thisCopyPath(): string
    {
        return dirname(__DIR__, 2);
    }

    /**
     * The root namespace of this copy — `Codad5\WPToolkit` unscoped, `PluginA\WPToolkit` scoped.
     */
    public static function thisCopyNamespace(): string
    {
        return substr(__NAMESPACE__, 0, -strlen('\\Foundation'));
    }
}
