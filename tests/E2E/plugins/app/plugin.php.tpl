<?php
/**
 * Plugin Name: {{TITLE}}
 * Description: WPToolkit end-to-end fixture (built by tests/E2E/build-fixtures.php).
 * Requires PHP: 5.6
 */

if (!defined('ABSPATH')) {
    exit;
}

// PHP 5.6-safe and leaves no variable in WordPress's global scope. No Composer here: the guard's
// `namespaces` option loads this plugin's own classes (src/MarkerProvider.php).
call_user_func(require __DIR__ . '/lib/wptoolkit/bootstrap/guard.php', __FILE__, array_merge({{NEEDS}}, array(
    'namespaces' => array('Fixture\\{{ID}}\\' => __DIR__ . '/src'),
)), function () {
    require __DIR__ . '/src/boot.php';
});
