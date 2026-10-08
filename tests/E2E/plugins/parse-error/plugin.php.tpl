<?php
/**
 * Plugin Name: {{TITLE}}
 * Description: WPToolkit end-to-end fixture (built by tests/E2E/build-fixtures.php).
 * Requires PHP: 5.6
 */

if (!defined('ABSPATH')) {
    exit;
}

// PHP 5.6-safe and leaves no variable in WordPress's global scope.
call_user_func(require __DIR__ . '/lib/wptoolkit/bootstrap/guard.php', __FILE__, {{NEEDS}}, function () {
    require __DIR__ . '/src/broken.php';
});
