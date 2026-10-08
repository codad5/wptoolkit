<?php

declare(strict_types=1);

namespace Fixture\{{ID}};

use {{NS}}\Foundation\Application;
use {{NS}}\Foundation\HookRegistrar;
use {{NS}}\Foundation\ServiceProvider;

/**
 * Prints a marker in the footer so tests can see this plugin booted, and with which WPToolkit.
 */
final class MarkerProvider extends ServiceProvider
{
    public function boot(HookRegistrar $hooks): void
    {
        $hooks->addAction('wp_footer', static function (): void {
            echo '<!-- wptoolkit-fixture:{{NAME}}:' . esc_html(Application::VERSION) . ' -->';
        });
    }
}
