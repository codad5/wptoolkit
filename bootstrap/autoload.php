<?php

/**
 * Standalone autoloader for installs without Composer (ADR-0014).
 *
 * - Maps this copy's root namespace to its own `src/`.
 * - Idempotent per copy: requiring it twice, or alongside Composer, registers nothing new.
 * - Defines no global functions, classes, constants or variables (ADR-0005): the loader is an
 *   anonymous class that registers itself. The root namespace is read from a class reference, so
 *   `bin/scope.php` rewrites it along with everything else.
 *
 * Loaded by the guard only after the environment checks pass, so modern syntax is fine here.
 *
 * @license GPL-2.0-or-later
 */

declare(strict_types=1);

(new class {
    public string $wptoolkitDirectory = '';

    private string $prefix = '';

    public function register(): void
    {
        $this->wptoolkitDirectory = dirname(__DIR__);
        $this->prefix = substr(\Codad5\WPToolkit\Foundation\Application::class, 0, -strlen('Foundation\\Application'));

        foreach (spl_autoload_functions() as $loader) {
            if (is_object($loader) && property_exists($loader, 'wptoolkitDirectory') && $loader->wptoolkitDirectory === $this->wptoolkitDirectory) {
                return;
            }
        }

        spl_autoload_register($this);
    }

    public function __invoke(string $class): void
    {
        if (strncmp($class, $this->prefix, strlen($this->prefix)) !== 0) {
            return;
        }

        $file = $this->wptoolkitDirectory . '/src/' . str_replace('\\', '/', substr($class, strlen($this->prefix))) . '.php';
        if (is_file($file)) {
            require $file;
        }
    }
})->register();
