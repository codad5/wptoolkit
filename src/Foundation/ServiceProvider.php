<?php

/**
 * @license GPL-2.0-or-later
 */

declare(strict_types=1);

namespace Codad5\WPToolkit\Foundation;

/**
 * Groups the wiring for one feature.
 *
 * - `register()` runs as soon as the application boots: bind services, nothing else. Don't
 *   resolve services or call WordPress here — other providers may not be registered yet.
 * - `boot()` runs on `init`, after the consumer's text domain is loaded, so it may call `__()`,
 *   add hooks and register routes. Declare any services it needs as parameters; they are
 *   resolved from the container.
 * - `activate()` / `deactivate()` (optional) run on plugin activation and deactivation, with
 *   their parameters injected the same way. Uninstall needs a static handler instead: pass
 *   `'uninstall' => MyUninstaller::class` in the application config.
 */
abstract class ServiceProvider
{
    public function __construct(protected Application $app)
    {
    }

    public function register(): void
    {
    }
}
