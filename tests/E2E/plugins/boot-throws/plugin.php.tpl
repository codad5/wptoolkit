<?php
/**
 * Plugin Name: {{TITLE}}
 * Description: WPToolkit end-to-end fixture (built by tests/E2E/build-fixtures.php).
 */

if (!defined('ABSPATH')) {
    exit;
}

call_user_func(require __DIR__ . '/lib/wptoolkit/bootstrap/guard.php', __FILE__, {{NEEDS}}, function () {
    throw new RuntimeException('fixture boot failure');
});
