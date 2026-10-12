<?php

/**
 * PSR-4 loader for a plugin's own classes, for installs without Composer (ADR-0014): the guard's
 * `namespaces` option. Composer users don't need it — Composer already loads their classes.
 *
 * Returns a closure rather than defining anything, so it adds no global names (ADR-0005). Required
 * by the guard only after the environment checks pass, so modern syntax is fine here. The guard has
 * already validated the map: non-empty prefixes ending in a backslash, directories that exist.
 *
 * @license GPL-2.0-or-later
 */

declare(strict_types=1);

/**
 * @param array<string, string> $namespaces Prefix (with trailing backslash) => directory.
 */
return static function (array $namespaces): void {
    // Longest prefix first, so `Acme\Admin\` wins over `Acme\` for a class under both.
    uksort($namespaces, static fn (string $a, string $b): int => strlen($b) <=> strlen($a));

    spl_autoload_register(static function (string $class) use ($namespaces): void {
        // class_exists() can be handed any string; only real class names may become a file path.
        if (preg_match('/^[A-Za-z_][A-Za-z0-9_]*(\\\\[A-Za-z_][A-Za-z0-9_]*)*$/', $class) !== 1) {
            return;
        }

        foreach ($namespaces as $prefix => $directory) {
            if (strncmp($class, $prefix, strlen($prefix)) !== 0) {
                continue;
            }

            $file = $directory . '/' . str_replace('\\', '/', substr($class, strlen($prefix))) . '.php';
            if (is_file($file)) {
                require $file;
                return;
            }
        }
    });
};
